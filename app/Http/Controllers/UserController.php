<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $headerId = request()->header('X-Department-ID');
        if ($headerId !== null && $headerId !== '' && (int) $headerId > 0) {
            $departmentId = (int) $headerId;
            if ($user && ($user->hasRole(['super-admin', 'owner']) || $user->hasAccessToDepartment($departmentId))) {
                if (Department::whereKey($departmentId)->exists()) {
                    return User::query()->whereHas('departments', function ($q) use ($departmentId): void {
                        $q->where('departments.id', $departmentId);
                    });
                }
            }
        }

        $departmentId = $this->getEffectiveDepartmentId('Secretaria não identificada para listar usuários.');
        return User::query()->whereHas('departments', function ($q) use ($departmentId): void {
            $q->where('departments.id', $departmentId);
        });
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = ($user && $user->hasRole('super-admin'))
            ? User::query()
            : $this->departmentScope();

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
        $role = $request->input('role');

        if ($role === 'owner') {
            $departmentIds = Department::query()->pluck('id')->toArray();
            $primaryDepartmentId = $departmentIds[0] ?? null;
        } elseif ($authUser && $authUser->hasRole('super-admin')) {
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
        } else {
            $primaryDepartmentId = $this->getEffectiveDepartmentId('Secretaria não identificada para criar usuário.');
            $departmentIds = [$primaryDepartmentId];
        }

        $municipalityId = null;
        if (! empty($departmentIds)) {
            $firstDept = Department::find($departmentIds[0]);
            if ($firstDept) {
                $municipalityId = $firstDept->municipality_id;
            }
        }

        $data = $request->safe()->only(['name', 'email', 'role', 'operation_pin']);
        $data['password'] = $request->validated('password');
        $data['municipality_id'] = $municipalityId;
        if ($request->filled('operation_password')) {
            $data['operation_password'] = $request->validated('operation_password');
        }

        $user = DB::transaction(function () use ($data, $departmentIds, $primaryDepartmentId): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'municipality_id' => $data['municipality_id'] ?? null,
                'operation_password' => $data['operation_password'] ?? null,
                'operation_pin' => isset($data['operation_pin']) && $data['operation_pin'] !== '' ? $data['operation_pin'] : null,
            ]);

            $user->assignRole($data['role']);

            if (! empty($departmentIds)) {
                $pivotData = [];
                foreach ($departmentIds as $departmentId) {
                    $pivotData[$departmentId] = ['is_primary' => $departmentId === $primaryDepartmentId];
                }
                $user->departments()->attach($pivotData);
            }

            return $user->load('roles', 'departments');
        });

        return response()->json(new UserResource($user), 201);
    }

    public function show(string $id): JsonResponse
    {
        $authUser = auth()->user();
        $userId = (int) $id;

        if ($authUser && $authUser->id === $userId) {
            $user = User::query()->with('roles', 'departments')->findOrFail($userId);
            return response()->json(new UserResource($user));
        }

        if ($authUser && $authUser->hasRole('super-admin')) {
            $user = User::query()->with('roles', 'departments')->findOrFail($userId);
        } else {
            $user = $this->departmentScope()->where('id', '!=', $authUser->id)->with('roles', 'departments')->findOrFail($userId);
        }

        return response()->json(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = $this->departmentScope()->with('roles', 'departments')->findOrFail((int) $id);

        $data = $request->safe()->only(['name', 'email', 'role', 'department_id', 'operation_pin']);
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

            if ($authUser && $authUser->hasRole('super-admin') && $request->filled('department_ids')) {
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

            $user->update($payload);
            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
            }
        });

        $user->refresh()->load('roles', 'departments');

        return response()->json(new UserResource($user));
    }

    public function destroy(string $id): JsonResponse
    {
        $user = $this->departmentScope()->findOrFail((int) $id);
        $authUser = auth()->user();

        if ($user->id === $authUser->id) {
            throw ValidationException::withMessages([
                'user' => ['Não é permitido excluir o próprio usuário.'],
            ]);
        }

        if ($authUser && $authUser->hasRole('owner') && $user->hasRole('super-admin')) {
            throw ValidationException::withMessages([
                'user' => ['Gestor(a) Geral não pode excluir Super Admin.'],
            ]);
        }

        $user->delete();

        return response()->json(['message' => 'Usuário excluído com sucesso.']);
    }
}
