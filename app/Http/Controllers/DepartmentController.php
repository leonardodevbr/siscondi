<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('departments.view');
        
        $user = auth()->user();
        $query = Department::query();

        // Super-admin vê todas as secretarias
        if (!$user->hasRole('super-admin')) {
            // Admin vê apenas secretarias do seu município
            if ($user->hasRole('admin') && $user->municipality_id) {
                $query->where('municipality_id', $user->municipality_id);
            } else {
                // Outros usuários veem apenas as secretarias que têm acesso
                $departmentIds = $user->getDepartmentIds();
                $query->whereIn('id', $departmentIds);
            }
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('is_main', 'desc')->orderBy('name', 'asc');

        if ($request->boolean('all') || ! $request->has('page')) {
            return response()->json(DepartmentResource::collection($query->get()));
        }

        return response()->json(DepartmentResource::collection($query->paginate(15)));
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $this->authorize('departments.create');
        
        $user = auth()->user();
        $data = $request->validated();
        
        // Super-admin pode criar secretaria em qualquer município
        if (!$user->hasRole('super-admin')) {
            // Admin só pode criar secretarias no seu município
            if ($user->hasRole('admin')) {
                if (!isset($data['municipality_id']) || $data['municipality_id'] !== $user->municipality_id) {
                    abort(403, 'Você só pode criar secretarias no seu município.');
                }
            } else {
                // Outros perfis não podem criar secretarias
                abort(403, 'Você não tem permissão para criar secretarias.');
            }
        }

        $department = Department::create($data);

        if (! empty($data['is_main']) && $department->municipality_id) {
            Department::query()
                ->where('municipality_id', $department->municipality_id)
                ->where('id', '!=', $department->id)
                ->update(['is_main' => false]);
        }

        return response()->json(new DepartmentResource($department), 201);
    }

    private function ensureDepartmentScope(Department $department): void
    {
        $user = auth()->user();
        
        // Super-admin tem acesso total
        if ($user && $user->hasRole('super-admin')) {
            return;
        }
        
        // Admin tem acesso às secretarias do seu município
        if ($user && $user->hasRole('admin') && $user->municipality_id === $department->municipality_id) {
            return;
        }
        
        // Outros usuários só têm acesso às secretarias vinculadas
        if ($user && $user->hasAccessToDepartment($department->id)) {
            return;
        }
        
        abort(403, 'Você não tem permissão para acessar esta secretaria.');
    }

    public function show(string|int $department): JsonResponse
    {
        $department = Department::query()->findOrFail((int) $department);
        $this->authorize('departments.view');
        $this->ensureDepartmentScope($department);

        return response()->json(new DepartmentResource($department));
    }

    public function update(UpdateDepartmentRequest $request, string|int $department): JsonResponse
    {
        $this->authorize('departments.edit');
        
        $user = auth()->user();
        $department = Department::query()->findOrFail((int) $department);
        $this->ensureDepartmentScope($department);
        $data = $request->validated();
        
        // Super-admin pode atualizar qualquer secretaria
        if (!$user->hasRole('super-admin')) {
            // Admin só pode atualizar secretarias do seu município
            if ($user->hasRole('admin')) {
                if (isset($data['municipality_id']) && $data['municipality_id'] !== $user->municipality_id) {
                    abort(403, 'Você não pode transferir secretarias para outro município.');
                }
            } else {
                // Outros perfis não podem atualizar secretarias
                abort(403, 'Você não tem permissão para atualizar secretarias.');
            }
        }

        if (! empty($data['is_main']) && $department->municipality_id) {
            Department::query()
                ->where('municipality_id', $department->municipality_id)
                ->where('id', '!=', $department->id)
                ->update(['is_main' => false]);
        }

        $department->update($data);

        return response()->json(new DepartmentResource($department->fresh()));
    }

    public function destroy(string|int $department): JsonResponse
    {
        $department = Department::query()->findOrFail((int) $department);
        $this->authorize('departments.delete');
        $this->ensureDepartmentScope($department);

        if ($department->is_main) {
            return response()->json([
                'message' => 'Não é possível excluir a secretaria principal.',
            ], 422);
        }

        if ($department->servants()->exists()) {
            return response()->json([
                'message' => 'Não é possível excluir uma secretaria com servidores lotados.',
            ], 422);
        }

        $department->delete();

        return response()->json(['message' => 'Secretaria excluída com sucesso.']);
    }
}
