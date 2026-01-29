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
     * Branch ID efetivo: para super-admin/owner usa X-Branch-ID do header; senão usa branch_id do usuário.
     *
     * @throws ValidationException quando não houver filial identificada
     */
    private function getEffectiveBranchId(string $message = 'Filial não identificada para listar usuários.'): int
    {
        $user = auth()->user();
        if (! $user) {
            throw ValidationException::withMessages(['branch' => [$message]]);
        }

        // Super Admin e Owner usam X-Branch-ID do header
        if ($user->hasRole(['super-admin', 'owner'])) {
            $headerId = request()->header('X-Branch-ID');
            if ($headerId !== null && $headerId !== '' && (int) $headerId > 0) {
                $id = (int) $headerId;
                if (Branch::whereKey($id)->exists()) {
                    return $id;
                }
            }
        }

        $branch = $user->getPrimaryBranch();
        if ($branch) {
            return $branch->id;
        }

        throw ValidationException::withMessages(['branch' => [$message]]);
    }

    /**
     * Scope de usuários pela filial.
     * Sempre filtra pela filial do header (X-Branch-ID) quando disponível.
     * Inclui usuários que têm vínculo direto (branch_id) OU através da tabela pivot (branch_user).
     * Caso contrário, usa a filial do usuário logado.
     */
    private function branchScope(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        
        // Tenta usar X-Branch-ID do header primeiro
        $headerId = request()->header('X-Branch-ID');
        if ($headerId !== null && $headerId !== '' && (int) $headerId > 0) {
            $branchId = (int) $headerId;
            
            // Verifica se o usuário tem acesso a essa filial
            if ($user && ($user->hasRole(['super-admin', 'owner']) || $user->hasAccessToBranch($branchId))) {
                if (Branch::whereKey($branchId)->exists()) {
                    return User::query()->whereHas('branches', function ($q) use ($branchId): void {
                        $q->where('branches.id', $branchId);
                    });
                }
            }
        }

        // Fallback: usa a filial do usuário logado
        $branchId = $this->getEffectiveBranchId('Filial não identificada para listar usuários.');
        return User::query()->whereHas('branches', function ($q) use ($branchId): void {
            $q->where('branches.id', $branchId);
        });
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->branchScope()->with('roles', 'branches');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->where('id', '!=', auth()->user()->id)->orderBy('name')->paginate(15);

        return response()->json(UserResource::collection($users));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $authUser = auth()->user();
        
        // Determina as filiais a vincular
        $branchIds = [];
        $primaryBranchId = null;
        $role = $request->input('role');
        
        // Owner sempre recebe TODAS as filiais automaticamente
        if ($role === 'owner') {
            $branchIds = Branch::query()->pluck('id')->toArray();
            $primaryBranchId = $branchIds[0] ?? null;
        } elseif ($authUser && $authUser->hasRole('super-admin')) {
            // Super Admin pode definir múltiplas filiais para outros roles
            if ($request->filled('branch_ids')) {
                $branchIds = $request->input('branch_ids');
                // Valida se todas as filiais existem
                $validBranches = Branch::whereIn('id', $branchIds)->pluck('id')->toArray();
                if (count($validBranches) !== count($branchIds)) {
                    throw ValidationException::withMessages(['branch_ids' => ['Uma ou mais filiais são inválidas.']]);
                }
            }
            
            // Define a filial primária
            $primaryBranchId = $request->filled('primary_branch_id') 
                ? (int) $request->input('primary_branch_id')
                : ($branchIds[0] ?? null);
        } else {
            // Outros usuários: nova conta herda a filial do criador
            $primaryBranchId = $this->getEffectiveBranchId('Filial não identificada para criar usuário.');
            $branchIds = [$primaryBranchId];
        }

        $data = $request->safe()->only(['name', 'email', 'role', 'operation_pin']);
        $data['password'] = $request->validated('password');
        if ($request->filled('operation_password')) {
            $data['operation_password'] = $request->validated('operation_password');
        }

        $user = DB::transaction(function () use ($data, $branchIds, $primaryBranchId): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'operation_password' => $data['operation_password'] ?? null,
                'operation_pin' => isset($data['operation_pin']) && $data['operation_pin'] !== '' ? $data['operation_pin'] : null,
            ]);

            $user->assignRole($data['role']);

            if (! empty($branchIds)) {
                $pivotData = [];
                foreach ($branchIds as $branchId) {
                    $pivotData[$branchId] = ['is_primary' => $branchId === $primaryBranchId];
                }
                $user->branches()->attach($pivotData);
            }

            return $user->load('roles', 'branches');
        });

        return response()->json(new UserResource($user), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $authUser = auth()->user();
        $userId = (int) $id;
        
        // Usuário pode sempre ver o próprio perfil
        if ($authUser && $authUser->id === $userId) {
            $user = User::query()->with('roles', 'branches')->findOrFail($userId);
            return response()->json(new UserResource($user));
        }

        if ($authUser && $authUser->hasRole('super-admin')) {
            $user = User::query()->with('roles', 'branches')->findOrFail($userId);
        } else {
            $user = $this->branchScope()->where('id', '!=', $authUser->id)->with('roles', 'branches')->findOrFail($userId);
        }

        return response()->json(new UserResource($user));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = $this->branchScope()->with('roles', 'branches')->findOrFail((int) $id);

        $data = $request->safe()->only(['name', 'email', 'role', 'branch_id', 'operation_pin']);
        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }
        if ($request->has('operation_password')) {
            $data['operation_password'] = $request->filled('operation_password')
                ? $request->validated('operation_password')
                : null;
        }

        $authUser = auth()->user();
        DB::transaction(function () use ($user, $data, $authUser, $request): void {
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
            if (array_key_exists('operation_pin', $data)) {
                $payload['operation_pin'] = $data['operation_pin'] !== null && $data['operation_pin'] !== '' ? $data['operation_pin'] : null;
            }
            
            if ($authUser && $authUser->hasRole('super-admin') && $request->filled('branch_ids')) {
                $branchIds = $request->input('branch_ids');
                $primaryBranchId = $request->filled('primary_branch_id')
                    ? (int) $request->input('primary_branch_id')
                    : ($branchIds[0] ?? null);
                $pivotData = [];
                foreach ($branchIds as $branchId) {
                    $pivotData[$branchId] = ['is_primary' => $branchId === $primaryBranchId];
                }
                $user->branches()->sync($pivotData);
            }
            
            $user->update($payload);
            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
            }
        });

        $user->refresh()->load('roles', 'branches');

        return response()->json(new UserResource($user));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = $this->branchScope()->findOrFail((int) $id);
        $authUser = auth()->user();

        if ($user->id === $authUser->id) {
            throw ValidationException::withMessages([
                'user' => ['Não é permitido excluir o próprio usuário.'],
            ]);
        }

        // Owner NÃO pode excluir Super-Admin
        if ($authUser && $authUser->hasRole('owner') && $user->hasRole('super-admin')) {
            throw ValidationException::withMessages([
                'user' => ['Gestor(a) Geral não pode excluir Super Admin.'],
            ]);
        }

        $user->delete();

        return response()->json(['message' => 'Usuário excluído com sucesso.']);
    }
}
