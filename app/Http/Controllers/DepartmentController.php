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

        if ($user && ! $user->hasRole('super-admin')) {
            $municipality = $user->getMunicipality();
            if ($municipality) {
                $query->where('municipality_id', $municipality->id);
            } else {
                $query->whereRaw('1 = 0');
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
        $data = $request->validated();
        $user = auth()->user();
        if ($user && ! $user->hasRole('super-admin')) {
            $municipality = $user->getMunicipality();
            if (! $municipality) {
                return response()->json(['message' => 'Usuário sem município vinculado (secretaria principal).'], 422);
            }
            $data['municipality_id'] = $municipality->id;
        }

        $department = Department::create($data);

        return response()->json(new DepartmentResource($department), 201);
    }

    private function ensureDepartmentScope(Department $department): void
    {
        $user = auth()->user();
        if ($user && $user->hasRole('super-admin')) {
            return;
        }
        $municipality = $user?->getMunicipality();
        if (! $municipality || $department->municipality_id !== $municipality->id) {
            abort(403, 'Secretaria fora do seu município.');
        }
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
        $user = auth()->user();
        if ($user && ! $user->hasRole('super-admin')) {
            unset($data['municipality_id']);
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
