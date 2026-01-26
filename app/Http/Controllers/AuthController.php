<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Branch;
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
     * Validar senha de operação do usuário logado.
     * A senha é armazenada com bcrypt (cast 'hashed' no model). Hash::check(plain, hash).
     */
    public function validateOperationPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['valid' => false]);
        }

        $plain = $request->input('password');
        $stored = $user->getRawOriginal('operation_password') ?? $user->operation_password;

        if ($stored === null || $stored === '') {
            return response()->json(['valid' => false]);
        }

        $valid = Hash::check($plain, $stored);

        return response()->json(['valid' => $valid]);
    }
}
