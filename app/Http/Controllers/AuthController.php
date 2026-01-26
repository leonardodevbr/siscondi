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

        $user->load('branch');

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
        $user?->load('branch');

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
     * Validar senha de operação para autorizar ação (cancelar item, desconto, saldo, etc.).
     *
     * - GERENTE/Super-admin logado: valida a senha de operação DELE MESMO.
     * - VENDEDOR (ou outro) logado: a senha digitada deve ser a de algum GERENTE ou Super-admin
     *   (mesma filial do vendedor, ou super-admin). Assim o vendedor chama um gerente para autorizar.
     */
    public function validateOperationPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['valid' => false, 'message' => 'Usuário não autenticado.']);
        }

        $plain = $request->input('password');
        $isManagerOrSuperAdmin = $user->hasRole('super-admin') || $user->hasRole('manager');

        if ($isManagerOrSuperAdmin) {
            $stored = $user->getRawOriginal('operation_password') ?? $user->operation_password;
            if ($stored === null || $stored === '') {
                return response()->json([
                    'valid' => false,
                    'message' => 'Este usuário não possui senha de operação cadastrada.',
                    'user_id' => $user->id,
                ]);
            }
            $valid = Hash::check($plain, $stored);

            return response()->json([
                'valid' => $valid,
                'user_id' => $user->id,
            ]);
        }

        // Vendedor/estoquista logado: a senha digitada deve ser de algum gerente (ou super-admin)
        $branchId = $user->branch_id;
        $managers = User::query()
            ->whereNotNull('operation_password')
            ->where('operation_password', '!=', '')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['manager', 'super-admin']))
            ->when($branchId !== null, function ($q) use ($branchId) {
                $q->where(function ($sub) use ($branchId) {
                    $sub->where('branch_id', $branchId)
                        ->orWhereHas('roles', fn ($r) => $r->where('name', 'super-admin'));
                });
            })
            ->get();

        foreach ($managers as $manager) {
            $stored = $manager->getRawOriginal('operation_password') ?? $manager->operation_password;
            if ($stored && Hash::check($plain, $stored)) {
                return response()->json([
                    'valid' => true,
                    'authorized_by_user_id' => $manager->id,
                ]);
            }
        }

        return response()->json([
            'valid' => false,
            'message' => 'Senha de gerente incorreta.',
        ]);
    }
}
