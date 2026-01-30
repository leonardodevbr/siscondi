<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreLegislationRequest;
use App\Http\Requests\UpdateLegislationRequest;
use App\Http\Resources\LegislationResource;
use App\Models\Legislation;
use App\Models\LegislationItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegislationController extends Controller
{
    /**
     * Lista todos os tipos de destino (labels) usados nas legislações ativas.
     * Usado no formulário de solicitação de diárias para popular o campo "Tipo de destino".
     */
    public function destinationTypes(Request $request): JsonResponse
    {
        $this->authorize('legislations.view');

        $labels = Legislation::query()
            ->where('is_active', true)
            ->get()
            ->pluck('destinations')
            ->filter()
            ->flatten()
            ->unique()
            ->values()
            ->all();

        if (empty($labels)) {
            $labels = LegislationItem::query()
                ->whereHas('legislation', fn ($q) => $q->where('is_active', true))
                ->get()
                ->pluck('values')
                ->filter()
                ->flatMap(fn ($v) => is_array($v) ? array_keys($v) : [])
                ->unique()
                ->values()
                ->all();
        }

        return response()->json(['data' => $labels]);
    }

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
            $legislationItem = $legislation->items()->create([
                'functional_category' => $item['functional_category'],
                'daily_class' => $item['daily_class'],
                'values' => $item['values'] ?? [],
            ]);
            $cargoIdsRaw = $item['cargo_ids'] ?? [];
            $cargoIds = array_values(array_unique(array_map('intval', $cargoIdsRaw)));
            $legislationItem->cargos()->sync($cargoIds);
        }

        $legislation->load(['items', 'items.cargos']);
        return response()->json(new LegislationResource($legislation), 201);
    }

    public function show(string|int $legislation): JsonResponse
    {
        $legislation = Legislation::query()->with(['items', 'items.cargos'])->findOrFail((int) $legislation);
        $this->authorize('legislations.view');

        return response()->json(new LegislationResource($legislation));
    }

    public function update(UpdateLegislationRequest $request, string|int $legislation): JsonResponse
    {
        $legislation = Legislation::query()->findOrFail((int) $legislation);
        $data = $request->validated();
        $itemsPayload = $data['items'] ?? [];
        unset($data['items']);

        $legislation->update($data);

        $keptItemIds = [];

        foreach ($itemsPayload as $item) {
            $cargoIdsSent = array_key_exists('cargo_ids', $item);
            $cargoIdsRaw = $item['cargo_ids'] ?? [];
            $cargoIds = array_values(array_unique(array_map('intval', $cargoIdsRaw)));
            $attrs = [
                'functional_category' => $item['functional_category'] ?? '',
                'daily_class' => $item['daily_class'] ?? '',
                'values' => $item['values'] ?? [],
            ];

            $legislationItem = null;
            if (! empty($item['id'])) {
                $legislationItem = $legislation->items()->find((int) $item['id']);
            }
            if (! $legislationItem) {
                $legislationItem = $legislation->items()
                    ->where('functional_category', $attrs['functional_category'])
                    ->where('daily_class', $attrs['daily_class'])
                    ->first();
            }

            if ($legislationItem) {
                $legislationItem->update($attrs);
                if ($cargoIdsSent) {
                    $legislationItem->cargos()->sync($cargoIds);
                }
                $keptItemIds[] = $legislationItem->id;
            } else {
                $legislationItem = $legislation->items()->create($attrs);
                $legislationItem->cargos()->sync($cargoIds);
                $keptItemIds[] = $legislationItem->id;
            }
        }

        $legislation->items()->whereNotIn('id', $keptItemIds)->delete();

        $legislation->load(['items', 'items.cargos']);
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
