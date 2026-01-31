<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCargoRequest;
use App\Http\Requests\UpdateCargoRequest;
use App\Http\Resources\CargoResource;
use App\Models\Cargo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CargoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('cargos.view');

        $query = Cargo::query()->with('legislationItems');

        // Removida filtragem por município para permitir acesso total

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('symbol', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $query->orderBy('symbol', 'asc');

        if ($request->boolean('all') || ! $request->has('page')) {
            return response()->json(CargoResource::collection($query->get()));
        }

        return response()->json(CargoResource::collection($query->paginate(15)));
    }

    public function store(StoreCargoRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        // Removida restrição de município para permitir criação livre

        $cargo = Cargo::create($data);

        return response()->json(new CargoResource($cargo), 201);
    }

    private function ensureCargoScope(Cargo $cargo): void
    {
        // Removida restrição de acesso por município
        return;
    }

    public function show(string|int $cargo): JsonResponse
    {
        $cargo = Cargo::query()->with('legislationItems')->findOrFail((int) $cargo);
        $this->authorize('cargos.view');
        $this->ensureCargoScope($cargo);

        return response()->json(new CargoResource($cargo));
    }

    public function update(UpdateCargoRequest $request, string|int $cargo): JsonResponse
    {
        $cargo = Cargo::query()->findOrFail((int) $cargo);
        $this->ensureCargoScope($cargo);
        $data = $request->validated();
        
        // Removida restrição de município para permitir atualização livre

        $cargo->update($data);

        return response()->json(new CargoResource($cargo->fresh()));
    }

    public function destroy(string|int $cargo): JsonResponse
    {
        $cargo = Cargo::query()->findOrFail((int) $cargo);
        $this->authorize('cargos.delete');
        $this->ensureCargoScope($cargo);

        if ($cargo->servants()->exists()) {
            return response()->json([
                'message' => 'Não é possível excluir um cargo com servidores vinculados.',
            ], 422);
        }

        $cargo->legislationItems()->detach();
        $cargo->delete();

        return response()->json(['message' => 'Cargo excluído com sucesso.']);
    }
}
