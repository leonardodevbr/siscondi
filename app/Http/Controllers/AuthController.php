<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle a login request to the application.
     */
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

        $user->load(['branches', 'roles']);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }

    /**
     * Get the authenticated user.
     * Para super-admin com X-Branch-ID no header, retorna a filial atual (em que está "contextado").
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user?->load(['branches', 'roles']);

        $payload = (new UserResource($user))->toArray($request);

        if ($user && $user->hasRole('super-admin')) {
            $headerId = $request->header('X-Branch-ID');
            if ($headerId !== null && $headerId !== '' && (int) $headerId > 0) {
                $branch = Branch::find((int) $headerId);
                if ($branch) {
                    $payload['branch_id'] = $branch->id;
                    $payload['branch'] = ['id' => $branch->id, 'name' => $branch->name];
                }
            }
        }

        return response()->json(['user' => $payload]);
    }

    /**
     * Validar autorização do gerente: PIN + senha de operação.
     * O gerente é quem digita (vendedor aciona, gerente vem e insere). 1 query por PIN, 1 Hash::check.
     * Retorna authorized_by_user_id para vincular a ação ao usuário que autorizou.
     */
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
        $branchId = $user->getPrimaryBranch()?->id;

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

        if ($branchId !== null && ! $manager->hasRole('super-admin') && (int) ($manager->getPrimaryBranch()?->id) !== (int) $branchId) {
            return response()->json([
                'valid' => false,
                'message' => 'Gerente de outra filial.',
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
