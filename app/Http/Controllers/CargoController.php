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

        $user = auth()->user();
        $query = Cargo::query()->with('legislationItems');

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
        $user = auth()->user();
        if ($user && ! $user->hasRole('super-admin')) {
            $municipality = $user->getMunicipality();
            if (! $municipality) {
                return response()->json(['message' => 'Usuário sem município vinculado.'], 422);
            }
            $data['municipality_id'] = $municipality->id;
        }

        $cargo = Cargo::create($data);

        return response()->json(new CargoResource($cargo), 201);
    }

    private function ensureCargoScope(Cargo $cargo): void
    {
        $user = auth()->user();
        if ($user && $user->hasRole('super-admin')) {
            return;
        }
        $municipality = $user?->getMunicipality();
        if (! $municipality || $cargo->municipality_id !== $municipality->id) {
            abort(403, 'Cargo fora do seu município.');
        }
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
        $user = auth()->user();
        if ($user && ! $user->hasRole('super-admin')) {
            unset($data['municipality_id']);
        }

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
