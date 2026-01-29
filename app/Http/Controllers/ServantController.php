<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreServantRequest;
use App\Http\Requests\UpdateServantRequest;
use App\Http\Resources\ServantResource;
use App\Models\Servant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('servants.view');

        $query = Servant::with(['legislationItem', 'department', 'user', 'cargos']);

        if ($request->has('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('cpf', 'like', "%{$search}%")
                  ->orWhere('matricula', 'like', "%{$search}%");
            });
        }

        if ($request->has('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->has('legislation_item_id')) {
            $query->where('legislation_item_id', $request->integer('legislation_item_id'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $query->orderBy('name', 'asc');

        if ($request->boolean('all') || !$request->has('page')) {
            $servants = $query->get();
            return response()->json(ServantResource::collection($servants));
        }

        return response()->json(ServantResource::collection($query->paginate(15)));
    }

    public function store(StoreServantRequest $request): JsonResponse
    {
        $data = $request->validated();
        $cargoIds = $data['cargo_ids'] ?? [];
        unset($data['cargo_ids']);
        $servant = Servant::create($data);
        $servant->cargos()->sync($cargoIds);
        $servant->load(['legislationItem', 'department', 'user', 'cargos']);

        return response()->json(new ServantResource($servant), 201);
    }

    public function show(string|int $servant): JsonResponse
    {
        $servant = Servant::query()
            ->with(['legislationItem', 'department', 'user', 'user.roles', 'user.departments', 'cargos'])
            ->findOrFail((int) $servant);
        $this->authorize('servants.view');

        return response()->json(new ServantResource($servant));
    }

    public function update(UpdateServantRequest $request, string|int $servant): JsonResponse
    {
        $servant = Servant::query()->findOrFail((int) $servant);
        $data = $request->validated();
        $cargoIds = $data['cargo_ids'] ?? null;
        unset($data['cargo_ids']);
        $servant->update($data);
        if ($cargoIds !== null) {
            $servant->cargos()->sync($cargoIds);
        }
        $servant->load(['legislationItem', 'department', 'user', 'cargos']);

        return response()->json(new ServantResource($servant));
    }

    public function destroy(string|int $servant): JsonResponse
    {
        $servant = Servant::query()->findOrFail((int) $servant);
        $this->authorize('servants.delete');

        // Verifica se há solicitações de diárias vinculadas
        if ($servant->dailyRequests()->exists()) {
            return response()->json([
                'message' => 'Não é possível deletar um servidor com solicitações de diárias vinculadas.',
            ], 422);
        }

        $servant->delete();

        return response()->json(['message' => 'Servidor deletado com sucesso.']);
    }
}
