<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Mail\FirstAccessMail;
use App\Http\Resources\UserResource;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $this->getCredentials($request);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'login' => ['E-mail, usuário ou matrícula e/ou senha incorretos.'],
            ]);
        }

        $user = Auth::user();

        if (! $user) {
            throw ValidationException::withMessages([
                'login' => ['E-mail, usuário ou matrícula e/ou senha incorretos.'],
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

    /**
     * Monta as credenciais para tentativa de login.
     * Aceita: email, username ou matricula.
     */
    private function getCredentials(LoginRequest $request): array
    {
        $login = $request->input('login');
        $password = $request->input('password');

        // Tenta identificar o tipo de login fornecido
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : null;

        // Se não for email, verifica se é username ou matricula
        if (! $field) {
            // Verifica se existe um usuário com esse username
            $userByUsername = User::where('username', $login)->first();
            if ($userByUsername) {
                $field = 'username';
            } else {
                // Assume que é matrícula
                $field = 'matricula';
            }
        }

        return [
            $field => $login,
            'password' => $password,
        ];
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

    /**
     * Esqueci minha senha: envia link por e-mail.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ], [
            'email.exists' => 'Não encontramos um usuário com este e-mail.',
        ]);

        $user = User::where('email', $request->input('email'))->first();
        if (!$user) {
            return response()->json(['message' => 'Não encontramos um usuário com este e-mail.'], 422);
        }

        $token = Password::broker()->createToken($user);
        $resetUrl = rtrim(config('app.url'), '/') . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($user->email);

        Mail::to($user->email)->send(new FirstAccessMail($user, $resetUrl, false));

        return response()->json([
            'message' => 'Enviamos um link para redefinir sua senha no e-mail informado. Verifique sua caixa de entrada.',
        ]);
    }

    /**
     * Redefinir senha (primeiro acesso ou esqueci minha senha).
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ], [
            'email.exists' => 'E-mail não encontrado.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Link expirado ou inválido. Solicite uma nova redefinição de senha.',
            ], 422);
        }

        return response()->json([
            'message' => 'Senha redefinida com sucesso. Você já pode entrar com sua nova senha.',
        ]);
    }
}
