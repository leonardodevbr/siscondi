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

        // Super-admin vê todos os cargos
        if (!$user->hasRole('super-admin')) {
            // Admin vê apenas cargos do seu município
            if ($user->hasRole('admin') && $user->municipality_id) {
                $query->where('municipality_id', $user->municipality_id);
            } else {
                // Outros usuários veem cargos do seu município
                if ($user->municipality_id) {
                    $query->where('municipality_id', $user->municipality_id);
                }
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
        $this->authorize('cargos.create');
        
        $user = auth()->user();
        $data = $request->validated();
        
        // Super-admin pode criar cargo em qualquer município
        if (!$user->hasRole('super-admin')) {
            // Admin só pode criar cargos no seu município
            if ($user->hasRole('admin')) {
                if (!isset($data['municipality_id']) || $data['municipality_id'] !== $user->municipality_id) {
                    abort(403, 'Você só pode criar cargos no seu município.');
                }
            } else {
                // Outros perfis precisam ter município definido
                if (!isset($data['municipality_id'])) {
                    $data['municipality_id'] = $user->municipality_id;
                }
                if ($data['municipality_id'] !== $user->municipality_id) {
                    abort(403, 'Você só pode criar cargos no seu município.');
                }
            }
        }

        $cargo = Cargo::create($data);

        return response()->json(new CargoResource($cargo), 201);
    }

    private function ensureCargoScope(Cargo $cargo): void
    {
        $user = auth()->user();
        
        // Super-admin tem acesso total
        if ($user && $user->hasRole('super-admin')) {
            return;
        }
        
        // Admin tem acesso aos cargos do seu município
        if ($user && $user->hasRole('admin') && $user->municipality_id === $cargo->municipality_id) {
            return;
        }
        
        // Outros usuários têm acesso aos cargos do seu município
        if ($user && $user->municipality_id === $cargo->municipality_id) {
            return;
        }
        
        abort(403, 'Você não tem permissão para acessar este cargo.');
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
        $this->authorize('cargos.edit');
        
        $user = auth()->user();
        $cargo = Cargo::query()->findOrFail((int) $cargo);
        $this->ensureCargoScope($cargo);
        $data = $request->validated();
        
        // Super-admin pode atualizar qualquer cargo
        if (!$user->hasRole('super-admin')) {
            // Admin só pode atualizar cargos do seu município
            if ($user->hasRole('admin')) {
                if (isset($data['municipality_id']) && $data['municipality_id'] !== $user->municipality_id) {
                    abort(403, 'Você não pode transferir cargos para outro município.');
                }
            } else {
                // Outros perfis não podem mudar o município
                if (isset($data['municipality_id']) && $data['municipality_id'] !== $cargo->municipality_id) {
                    abort(403, 'Você não pode transferir cargos para outro município.');
                }
            }
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
