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
        $data['branch_id'] = $primaryBranchId; // Mantém para compatibilidade
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
                'branch_id' => $data['branch_id'],
            ]);
            
            $user->assignRole($data['role']);
            
            // Vincula as filiais na tabela pivot
            if (!empty($branchIds)) {
                $pivotData = [];
                foreach ($branchIds as $branchId) {
                    $pivotData[$branchId] = ['is_primary' => $branchId === $primaryBranchId];
                }
                $user->branches()->attach($pivotData);
            }

            return $user->load('roles', 'branch', 'branches');
        });

        return response()->json(new UserResource($user), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $authUser = auth()->user();
        
        // Super Admin pode ver qualquer usuário
        if ($authUser && $authUser->hasRole('super-admin')) {
            $user = User::query()->with('roles', 'branch', 'branches')->findOrFail((int) $id);
        } else {
            // Outros usuários: apenas da mesma filial
            $user = $this->branchScope()->with('roles', 'branch', 'branches')->findOrFail((int) $id);
        }

        return response()->json(new UserResource($user));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = $this->branchScope()->with('roles', 'branch', 'branches')->findOrFail((int) $id);

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
            
            // Super Admin pode alterar filiais
            if ($authUser && $authUser->hasRole('super-admin')) {
                // Atualiza branch_id (compatibilidade)
                if (array_key_exists('branch_id', $data)) {
                    $payload['branch_id'] = $data['branch_id'];
                }
                
                // Atualiza múltiplas filiais se enviado
                if ($request->filled('branch_ids')) {
                    $branchIds = $request->input('branch_ids');
                    $primaryBranchId = $request->filled('primary_branch_id') 
                        ? (int) $request->input('primary_branch_id')
                        : ($branchIds[0] ?? null);
                    
                    // Sincroniza filiais na tabela pivot
                    $pivotData = [];
                    foreach ($branchIds as $branchId) {
                        $pivotData[$branchId] = ['is_primary' => $branchId === $primaryBranchId];
                    }
                    $user->branches()->sync($pivotData);
                    
                    // Atualiza branch_id para a primária
                    $payload['branch_id'] = $primaryBranchId;
                }
            }
            
            $user->update($payload);
            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
            }
        });

        $user->refresh()->load('roles', 'branch', 'branches');

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
