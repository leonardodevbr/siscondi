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

        $query = Department::query();

        // Removida filtragem por município para permitir acesso total

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
        $data = $request->validated();
        
        // Removida restrição de município para permitir criação livre

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
        // Removida restrição de acesso por município
        return;
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
        $department = Department::query()->findOrFail((int) $department);
        $this->ensureDepartmentScope($department);
        $data = $request->validated();
        
        // Removida restrição de município para permitir atualização livre

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
