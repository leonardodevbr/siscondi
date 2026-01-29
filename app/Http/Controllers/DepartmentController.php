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

        if ($request->has('search')) {
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
        $department = Department::create($request->validated());

        return response()->json(new DepartmentResource($department), 201);
    }

    public function show(Department $department): JsonResponse
    {
        $this->authorize('departments.view');

        return response()->json(new DepartmentResource($department));
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $department->update($request->validated());

        return response()->json(new DepartmentResource($department));
    }

    public function destroy(Department $department): JsonResponse
    {
        $this->authorize('departments.delete');

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
