<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Department;
use App\Models\Servant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private function getEffectiveDepartmentId(string $message = 'Secretaria não identificada para listar usuários.'): int
    {
        $user = auth()->user();
        if (! $user) {
            throw ValidationException::withMessages(['department' => [$message]]);
        }

        if ($user->hasRole(['super-admin', 'owner'])) {
            $headerId = request()->header('X-Department-ID');
            if ($headerId !== null && $headerId !== '' && (int) $headerId > 0) {
                $id = (int) $headerId;
                if (Department::whereKey($id)->exists()) {
                    return $id;
                }
            }
        }

        $department = $user->getPrimaryDepartment();
        if ($department) {
            return $department->id;
        }

        throw ValidationException::withMessages(['department' => [$message]]);
    }

    private function departmentScope(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        $query = User::query();
        
        // Super-admin vê todos os usuários
        if ($user && $user->hasRole('super-admin')) {
            return $query;
        }
        
        // Admin vê usuários do seu município
        if ($user && $user->hasRole('admin') && $user->municipality_id) {
            return $query->where('municipality_id', $user->municipality_id);
        }
        
        // Outros usuários veem apenas usuários das secretarias que têm acesso
        if ($user) {
            $departmentIds = $user->getDepartmentIds();
            return $query->whereHas('departments', function ($q) use ($departmentIds): void {
                $q->whereIn('departments.id', $departmentIds);
            });
        }
        
        return $query->whereRaw('1 = 0'); // Retorna vazio se não autenticado
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = $this->departmentScope();

        $query->with('roles', 'departments');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->where('id', '!=', $user->id)->orderBy('name')->paginate(15);

        return response()->json(UserResource::collection($users));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $authUser = auth()->user();
        $departmentIds = [];
        $primaryDepartmentId = null;
        $roles = $request->validated('roles');

        // Verifica permissões para criar usuário
        if (!$authUser->hasRole('super-admin')) {
            // Admin só pode criar usuários no seu município
            if ($authUser->hasRole('admin')) {
                if ($request->filled('department_ids')) {
                    $deptIds = $request->input('department_ids');
                    $invalidDepts = Department::whereIn('id', $deptIds)
                        ->where('municipality_id', '!=', $authUser->municipality_id)
                        ->exists();
                    
                    if ($invalidDepts) {
                        throw ValidationException::withMessages([
                            'department_ids' => ['Você só pode criar usuários para secretarias do seu município.']
                        ]);
                    }
                }
            } else {
                // Outros perfis não podem criar usuários
                throw ValidationException::withMessages([
                    'permission' => ['Você não tem permissão para criar usuários.']
                ]);
            }
        }

        if ($request->filled('department_ids')) {
            $departmentIds = $request->input('department_ids');
            $validDepartments = Department::whereIn('id', $departmentIds)->pluck('id')->toArray();
            if (count($validDepartments) !== count($departmentIds)) {
                throw ValidationException::withMessages(['department_ids' => ['Uma ou mais secretarias são inválidas.']]);
            }
        }
        $primaryDepartmentId = $request->filled('primary_department_id')
            ? (int) $request->input('primary_department_id')
            : ($departmentIds[0] ?? null);

        $municipalityId = null;
        if (! empty($departmentIds)) {
            $firstDept = Department::find($departmentIds[0]);
            if ($firstDept) {
                $municipalityId = $firstDept->municipality_id;
            }
        }

        $data = $request->safe()->only(['name', 'email', 'operation_pin']);
        $data['password'] = $request->validated('password');
        $data['municipality_id'] = $municipalityId;
        if ($request->filled('operation_password')) {
            $data['operation_password'] = $request->validated('operation_password');
        }

        $user = DB::transaction(function () use ($data, $roles, $departmentIds, $primaryDepartmentId): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'municipality_id' => $data['municipality_id'] ?? null,
                'operation_password' => $data['operation_password'] ?? null,
                'operation_pin' => isset($data['operation_pin']) && $data['operation_pin'] !== '' ? $data['operation_pin'] : null,
            ]);

            $user->syncRoles($roles);

            if (! empty($departmentIds)) {
                $pivotData = [];
                foreach ($departmentIds as $departmentId) {
                    $pivotData[$departmentId] = ['is_primary' => $departmentId === $primaryDepartmentId];
                }
                $user->departments()->attach($pivotData);
            }

            return $user->load('roles', 'departments', 'cargo');
        });

        if ($request->filled('servant_id')) {
            $servant = Servant::find((int) $request->input('servant_id'));
            if ($servant) {
                $servant->update(['user_id' => $user->id, 'email' => $user->email]);
            }
        }

        if ($request->hasFile('signature')) {
            $path = $request->file('signature')->store('signatures', 'public');
            if ($path) {
                $user->update(['signature_path' => $path]);
            }
        }

        return response()->json(new UserResource($user->fresh()->load('servant')), 201);
    }

    public function show(string $id): JsonResponse
    {
        $authUser = auth()->user();
        $userId = (int) $id;

        if ($authUser && $authUser->id === $userId) {
            $user = User::query()->with('roles', 'departments', 'servant')->findOrFail($userId);
            return response()->json(new UserResource($user));
        }

        $user = $this->departmentScope()
            ->with('roles', 'departments', 'servant')
            ->findOrFail($userId);

        return response()->json(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $userId = (int) $id;
        $user = $this->departmentScope()
            ->with('roles', 'departments', 'servant')
            ->findOrFail($userId);

        $data = $request->safe()->only(['name', 'email', 'cargo_id', 'department_id', 'operation_pin', 'roles', 'servant_id', 'signature_path']);
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

            if ($request->filled('department_ids')) {
                $departmentIds = $request->input('department_ids');
                $primaryDepartmentId = $request->filled('primary_department_id')
                    ? (int) $request->input('primary_department_id')
                    : ($departmentIds[0] ?? null);
                $firstDept = Department::find($departmentIds[0] ?? null);
                if ($firstDept) {
                    $payload['municipality_id'] = $firstDept->municipality_id;
                }
                $pivotData = [];
                foreach ($departmentIds as $departmentId) {
                    $pivotData[$departmentId] = ['is_primary' => $departmentId === $primaryDepartmentId];
                }
                $user->departments()->sync($pivotData);
            }

            if (isset($data['roles'])) {
                $user->syncRoles($data['roles']);
            }
            $user->update($payload);

            if ($request->has('servant_id')) {
                Servant::where('user_id', $user->id)->update(['user_id' => null]);
                if ($request->filled('servant_id')) {
                    $servant = Servant::find((int) $request->input('servant_id'));
                    if ($servant) {
                        $servant->update(['user_id' => $user->id]);
                        if (array_key_exists('email', $payload)) {
                            $servant->update(['email' => $payload['email']]);
                        }
                    }
                }
            } elseif (array_key_exists('email', $payload) && $user->servant) {
                $user->servant->update(['email' => $payload['email']]);
            }
        });

        if ($request->hasFile('signature')) {
            $disk = Storage::disk('public');
            if ($user->signature_path && $disk->exists($user->signature_path)) {
                $disk->delete($user->signature_path);
            }
            $path = $request->file('signature')->store('signatures', 'public');
            if ($path) {
                $user->update(['signature_path' => $path]);
            }
        } elseif ($request->has('signature_path')) {
            $newPath = $request->input('signature_path');
            if ($newPath === null || $newPath === '') {
                $disk = Storage::disk('public');
                if ($user->signature_path && $disk->exists($user->signature_path)) {
                    $disk->delete($user->signature_path);
                }
                $user->update(['signature_path' => null]);
            }
        }

        $user->refresh()->load('roles', 'departments', 'servant');

        return response()->json(new UserResource($user));
    }

    public function destroy(string $id): JsonResponse
    {
        $authUser = auth()->user();
        $userId = (int) $id;
        $user = $this->departmentScope()->findOrFail($userId);

        if ($user->id === $authUser->id) {
            throw ValidationException::withMessages([
                'user' => ['Não é permitido excluir o próprio usuário.'],
            ]);
        }

        // Super-admin pode excluir qualquer usuário (exceto ele mesmo)
        if (!$authUser->hasRole('super-admin')) {
            // Admin só pode excluir usuários do seu município
            if ($authUser->hasRole('admin')) {
                if ($user->municipality_id !== $authUser->municipality_id) {
                    throw ValidationException::withMessages([
                        'user' => ['Você só pode excluir usuários do seu município.'],
                    ]);
                }
            } else {
                // Outros perfis não podem excluir usuários
                throw ValidationException::withMessages([
                    'permission' => ['Você não tem permissão para excluir usuários.'],
                ]);
            }
        }

        $user->delete();

        return response()->json(['message' => 'Usuário excluído com sucesso.']);
    }
}
