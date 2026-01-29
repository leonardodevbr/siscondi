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

        $query = Legislation::with('items');

        if ($request->has('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('law_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $query->orderBy('title', 'asc');

        if ($request->boolean('all') || !$request->has('page')) {
            $legislations = $query->get();
            return response()->json(LegislationResource::collection($legislations));
        }

        return response()->json(LegislationResource::collection($query->paginate(15)));
    }

    public function store(StoreLegislationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']);

        $legislation = Legislation::create($data);

        foreach ($items as $item) {
            $legislation->items()->create([
                'functional_category' => $item['functional_category'],
                'daily_class' => $item['daily_class'],
                'values' => $item['values'] ?? [],
            ]);
        }

        $legislation->load('items');
        return response()->json(new LegislationResource($legislation), 201);
    }

    public function show(string|int $legislation): JsonResponse
    {
        $legislation = Legislation::query()->with('items')->findOrFail((int) $legislation);
        $this->authorize('legislations.view');

        return response()->json(new LegislationResource($legislation));
    }

    public function update(UpdateLegislationRequest $request, string|int $legislation): JsonResponse
    {
        $legislation = Legislation::query()->findOrFail((int) $legislation);
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']);

        $legislation->update($data);

        $legislation->items()->delete();
        foreach ($items as $item) {
            $legislation->items()->create([
                'functional_category' => $item['functional_category'],
                'daily_class' => $item['daily_class'],
                'values' => $item['values'] ?? [],
            ]);
        }

        $legislation->load('items');
        return response()->json(new LegislationResource($legislation));
    }

    public function destroy(string|int $legislation): JsonResponse
    {
        $legislation = Legislation::query()->findOrFail((int) $legislation);
        $this->authorize('legislations.delete');

        if ($legislation->servants()->exists()) {
            return response()->json([
                'message' => 'Não é possível deletar uma legislação com servidores vinculados.',
            ], 422);
        }

        $legislation->delete();

        return response()->json(['message' => 'Legislação deletada com sucesso.']);
    }
}
