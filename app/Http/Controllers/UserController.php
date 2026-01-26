<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Branch ID efetivo: para super-admin usa X-Branch-ID do header; senão usa branch_id do usuário.
     *
     * @throws ValidationException quando não houver filial identificada
     */
    private function getEffectiveBranchId(string $message = 'Filial não identificada para listar usuários.'): int
    {
        $user = auth()->user();
        if (! $user) {
            throw ValidationException::withMessages(['branch' => [$message]]);
        }

        if ($user->hasRole('super-admin')) {
            $headerId = request()->header('X-Branch-ID');
            if ($headerId !== null && $headerId !== '' && (int) $headerId > 0) {
                $id = (int) $headerId;
                if (Branch::whereKey($id)->exists()) {
                    return $id;
                }
            }
        }

        $branchId = $user->branch_id;
        if ($branchId) {
            return (int) $branchId;
        }

        throw ValidationException::withMessages(['branch' => [$message]]);
    }

    /**
     * Scope de usuários pela filial do logado. Super-admin usa filial do header X-Branch-ID.
     */
    private function branchScope(): \Illuminate\Database\Eloquent\Builder
    {
        $branchId = $this->getEffectiveBranchId('Filial não identificada para listar usuários.');

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
        $user = auth()->user();
        if ($user && $user->hasRole('super-admin') && $request->filled('branch_id')) {
            $branchId = (int) $request->input('branch_id');
            if (! Branch::whereKey($branchId)->exists()) {
                throw ValidationException::withMessages(['branch_id' => ['Filial inválida.']]);
            }
        } else {
            $branchId = $this->getEffectiveBranchId('Filial não identificada para criar usuário.');
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

        $data = $request->safe()->only(['name', 'email', 'role', 'branch_id']);
        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }
        if ($request->has('operation_password')) {
            $data['operation_password'] = $request->filled('operation_password')
                ? $request->validated('operation_password')
                : null;
        }

        $authUser = auth()->user();
        DB::transaction(function () use ($user, $data, $authUser): void {
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
            if ($authUser && $authUser->hasRole('super-admin') && array_key_exists('branch_id', $data)) {
                $payload['branch_id'] = $data['branch_id'];
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
