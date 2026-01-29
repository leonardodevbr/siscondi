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

        $query = Servant::with(['legislation', 'department', 'user']);

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

        if ($request->has('legislation_id')) {
            $query->where('legislation_id', $request->integer('legislation_id'));
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
        $servant = Servant::create($request->validated());
        $servant->load(['legislation', 'department', 'user']);

        return response()->json(new ServantResource($servant), 201);
    }

    public function show(Servant $servant): JsonResponse
    {
        $this->authorize('servants.view');

        $servant->load(['legislation', 'department', 'user']);

        return response()->json(new ServantResource($servant));
    }

    public function update(UpdateServantRequest $request, Servant $servant): JsonResponse
    {
        $servant->update($request->validated());
        $servant->load(['legislation', 'department', 'user']);

        return response()->json(new ServantResource($servant));
    }

    public function destroy(Servant $servant): JsonResponse
    {
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
