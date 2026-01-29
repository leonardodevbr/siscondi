<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->validated())) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        $user = Auth::user();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        $user->load(['departments', 'roles', 'municipality']);
        $user->update(['primary_department_id' => null]);

        $departments = $user->departments;
        if ($departments->count() === 1) {
            $user->update(['primary_department_id' => $departments->first()->id]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->fresh(['departments', 'roles', 'municipality'])),
            'needs_primary_department' => $user->needsPrimaryDepartmentChoice(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->update(['primary_department_id' => null]);
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }

    /**
     * Define a secretaria em que o usuário está atuando (obrigatório após login quando tem mais de uma).
     */
    public function setPrimaryDepartment(Request $request): JsonResponse
    {
        $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
        ]);
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }
        $departmentId = (int) $request->input('department_id');
        if (! $user->departments()->where('departments.id', $departmentId)->exists()) {
            return response()->json(['message' => 'Secretaria não vinculada ao usuário.'], 422);
        }
        $user->update(['primary_department_id' => $departmentId]);
        $user->load(['departments', 'roles', 'municipality']);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user?->load(['departments', 'roles', 'municipality']);

        $payload = (new UserResource($user))->toArray($request);

        if ($user && $user->hasRole('super-admin')) {
            $headerId = $request->header('X-Department-ID');
            if ($headerId !== null && $headerId !== '' && (int) $headerId > 0) {
                $department = Department::find((int) $headerId);
                if ($department) {
                    $payload['department_id'] = $department->id;
                    $payload['department'] = ['id' => $department->id, 'name' => $department->name];
                    $payload['primary_department_id'] = $department->id;
                }
            }
        }

        return response()->json(['user' => $payload]);
    }

    public function validateOperationPassword(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'string', 'max:10'],
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['valid' => false, 'message' => 'Usuário não autenticado.']);
        }

        $pin = trim($request->input('pin'));
        $plain = $request->input('password');
        $departmentId = $user->getPrimaryDepartment()?->id;

        $manager = User::query()
            ->where('operation_pin', $pin)
            ->whereNotNull('operation_password')
            ->where('operation_password', '!=', '')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['manager', 'super-admin']))
            ->first();

        if (! $manager) {
            return response()->json([
                'valid' => false,
                'message' => 'PIN inválido ou gerente sem senha de operação cadastrada.',
            ]);
        }

        if ($departmentId !== null && ! $manager->hasRole('super-admin') && (int) ($manager->getPrimaryDepartment()?->id) !== (int) $departmentId) {
            return response()->json([
                'valid' => false,
                'message' => 'Gerente de outra secretaria.',
            ]);
        }

        $stored = $manager->getRawOriginal('operation_password') ?? $manager->operation_password;
        if (! Hash::check($plain, $stored)) {
            return response()->json([
                'valid' => false,
                'message' => 'Senha de operação incorreta.',
            ]);
        }

        return response()->json([
            'valid' => true,
            'authorized_by_user_id' => $manager->id,
        ]);
    }
}
