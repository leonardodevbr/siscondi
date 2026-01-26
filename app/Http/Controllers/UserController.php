<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Scope de usuários pela filial do logado. Todas as operações são por branch.
     */
    private function branchScope()
    {
        $branchId = auth()->user()?->branch_id;
        if (! $branchId) {
            throw ValidationException::withMessages([
                'branch' => ['Filial não identificada para listar usuários.'],
            ]);
        }

        return User::query()->where('branch_id', $branchId);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->branchScope()->with('branch')->with('roles');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->paginate(15);

        return response()->json(UserResource::collection($users));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $branchId = auth()->user()?->branch_id;
        if (! $branchId) {
            throw ValidationException::withMessages([
                'branch' => ['Filial não identificada para criar usuário.'],
            ]);
        }

        $data = $request->safe()->only(['name', 'email', 'role']);
        $data['password'] = $request->validated('password');
        $data['branch_id'] = $branchId;
        if ($request->filled('operation_password')) {
            $data['operation_password'] = $request->validated('operation_password');
        }

        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'operation_password' => $data['operation_password'] ?? null,
                'branch_id' => $data['branch_id'],
            ]);
            $user->assignRole($data['role']);

            return $user->load('roles', 'branch');
        });

        return response()->json(new UserResource($user), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = $this->branchScope()->with('roles', 'branch')->findOrFail((int) $id);

        return response()->json(new UserResource($user));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = $this->branchScope()->with('roles', 'branch')->findOrFail((int) $id);

        $data = $request->safe()->only(['name', 'email', 'role']);
        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }
        if ($request->has('operation_password')) {
            $data['operation_password'] = $request->filled('operation_password')
                ? $request->validated('operation_password')
                : null;
        }

        DB::transaction(function () use ($user, $data): void {
            $payload = [
                'name' => $data['name'] ?? $user->name,
                'email' => $data['email'] ?? $user->email,
            ];
            if (isset($data['password']) && $data['password'] !== null) {
                $payload['password'] = $data['password'];
            }
            if (array_key_exists('operation_password', $data)) {
                $payload['operation_password'] = $data['operation_password'];
            }
            $user->update($payload);
            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
            }
        });

        $user->refresh()->load('roles', 'branch');

        return response()->json(new UserResource($user));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = $this->branchScope()->findOrFail((int) $id);

        if ($user->id === auth()->id()) {
            throw ValidationException::withMessages([
                'user' => ['Não é permitido excluir o próprio usuário.'],
            ]);
        }

        $user->delete();

        return response()->json(['message' => 'Usuário excluído com sucesso.']);
    }
}
