<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreLegislationRequest;
use App\Http\Requests\UpdateLegislationRequest;
use App\Http\Resources\LegislationResource;
use App\Models\Legislation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegislationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('legislations.view');

        $query = Legislation::query();

        if ($request->has('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('law_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $query->orderBy('code', 'asc');

        if ($request->boolean('all') || !$request->has('page')) {
            $legislations = $query->get();
            return response()->json(LegislationResource::collection($legislations));
        }

        return response()->json(LegislationResource::collection($query->paginate(15)));
    }

    public function store(StoreLegislationRequest $request): JsonResponse
    {
        $legislation = Legislation::create($request->validated());

        return response()->json(new LegislationResource($legislation), 201);
    }

    public function show(Legislation $legislation): JsonResponse
    {
        $this->authorize('legislations.view');

        return response()->json(new LegislationResource($legislation));
    }

    public function update(UpdateLegislationRequest $request, Legislation $legislation): JsonResponse
    {
        $legislation->update($request->validated());

        return response()->json(new LegislationResource($legislation));
    }

    public function destroy(Legislation $legislation): JsonResponse
    {
        $this->authorize('legislations.delete');

        // Verifica se há servidores vinculados
        if ($legislation->servants()->exists()) {
            return response()->json([
                'message' => 'Não é possível deletar uma legislação com servidores vinculados.',
            ], 422);
        }

        $legislation->delete();

        return response()->json(['message' => 'Legislação deletada com sucesso.']);
    }
}
