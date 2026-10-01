<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $data['login'])
            ->orWhere('username', $data['login'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => 'Credenciais inválidas.']);
        }

        return response()->json([
            'token' => $user->createToken('cultura-web')->plainTextToken,
            'user' => $user->load('roles', 'permissions'),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()->load('roles', 'permissions')]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
