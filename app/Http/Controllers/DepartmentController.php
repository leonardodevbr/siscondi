<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        $this->authorize('departments.view');
        
        $user = auth()->user();
        $query = Department::query()->withCount('servants');

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

        // Hierarquia: pai seguido dos seus filhos (subdepartamentos logo abaixo do parent)
        $query->orderByRaw('COALESCE(parent_id, id) ASC')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END ASC')
            ->orderBy('is_main', 'desc')
            ->orderBy('name', 'asc');

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 15;

        if ($request->boolean('all')) {
            return response()->json(DepartmentResource::collection($query->get()));
        }

        return DepartmentResource::collection($query->paginate($perPage));
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

        $updateData = $request->safe()->only([
            'municipality_id', 'parent_id', 'name', 'code', 'description', 'is_main',
            'fund_cnpj', 'fund_name', 'fund_code', 'logo_path',
            'address', 'neighborhood', 'zip_code', 'phone', 'email',
        ]);

        // Evitar que a secretaria seja pai de si mesma
        if (isset($updateData['parent_id']) && (int) $updateData['parent_id'] === (int) $department->id) {
            $updateData['parent_id'] = null;
        }
        // Evitar ciclo: pai não pode ser um subdepartamento desta secretaria
        if (! empty($updateData['parent_id'])) {
            $descendantIds = $this->getDescendantIds($department);
            if (in_array((int) $updateData['parent_id'], $descendantIds, true)) {
                $updateData['parent_id'] = $department->parent_id;
            }
        }

        $department->update($updateData);

        return response()->json(new DepartmentResource($department->fresh()));
    }

    /**
     * Retorna IDs de todos os descendentes (filhos, netos, etc.) do departamento.
     *
     * @return int[]
     */
    private function getDescendantIds(Department $department): array
    {
        $department->load('children');
        $ids = [];
        foreach ($department->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }
        return $ids;
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
