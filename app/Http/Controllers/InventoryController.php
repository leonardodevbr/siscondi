<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * Store a new inventory adjustment.
     */
    public function storeAdjustment(StoreInventoryAdjustmentRequest $request): JsonResponse
    {
        $movement = InventoryMovement::create([
            'product_id' => $request->product_id,
            'variation_id' => $request->variation_id,
            'user_id' => auth()->id(),
            'type' => $request->type,
            'quantity' => $request->quantity,
            'reason' => $request->reason,
        ]);

        $product = Product::with(['variants.inventories' => function ($query): void {
            $user = auth()->user();
            $branchId = $user?->branch_id ?? request()->header('X-Branch-ID');
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
        }])->findOrFail($request->product_id);

        if ($request->variation_id) {
            $variant = $product->variants()->find($request->variation_id);
            $inventory = $variant?->inventories->first();
            $currentStock = $inventory?->quantity ?? 0;
        } else {
            $variant = $product->variants()->first();
            $inventory = $variant?->inventories->first();
            $currentStock = $inventory?->quantity ?? 0;
        }

        return response()->json([
            'message' => 'Movimentação registrada com sucesso',
            'movement' => [
                'id' => $movement->id,
                'type' => $movement->type,
                'quantity' => $movement->quantity,
                'reason' => $movement->reason,
                'created_at' => $movement->created_at,
            ],
            'current_stock' => $currentStock,
        ], 201);
    }

    /**
     * Get inventory movement history for a product.
     */
    public function history(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'variation_id' => ['nullable', 'integer', 'exists:product_variants,id'],
        ]);

        $query = InventoryMovement::with(['user:id,name', 'variation:id,sku'])
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc');

        if ($request->has('variation_id') && $request->variation_id) {
            $query->where('variation_id', $request->variation_id);
        } else {
            $query->whereNull('variation_id');
        }

        $movements = $query->paginate(20);

        return response()->json([
            'data' => $movements->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'type' => $movement->type,
                    'type_label' => $this->getTypeLabel($movement->type),
                    'quantity' => $movement->quantity,
                    'reason' => $movement->reason,
                    'user' => $movement->user ? [
                        'id' => $movement->user->id,
                        'name' => $movement->user->name,
                    ] : null,
                    'variation' => $movement->variation ? [
                        'id' => $movement->variation->id,
                        'sku' => $movement->variation->sku,
                    ] : null,
                    'created_at' => $movement->created_at->format('d/m/Y H:i'),
                ];
            }),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    /**
     * Get all inventory movements with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryMovement::with(['user:id,name', 'product:id,name'])
            ->with('variation')
            ->orderBy('created_at', 'desc');

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search): void {
                $q->whereHas('product', function ($q2) use ($search): void {
                    $q2->where('name', 'like', "%{$search}%");
                });
            })->orWhere(function ($q) use ($search): void {
                $q->whereNotNull('variation_id')
                    ->whereHas('variation', function ($q2) use ($search): void {
                        $q2->where('sku', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $movements = $query->paginate(20);

        return response()->json([
            'data' => $movements->map(function ($movement) {
                $productName = $movement->product?->name ?? 'N/A';
                $variantInfo = '';
                
                if ($movement->variation) {
                    $attrs = $movement->variation->attributes ?? [];
                    $variantParts = [];
                    if (isset($attrs['cor'])) {
                        $variantParts[] = $attrs['cor'];
                    }
                    if (isset($attrs['tamanho'])) {
                        $variantParts[] = $attrs['tamanho'];
                    }
                    $variantInfo = count($variantParts) > 0 ? ' - ' . implode('/', $variantParts) : ' - ' . ($movement->variation->sku ?? '');
                }

                return [
                    'id' => $movement->id,
                    'type' => $movement->type,
                    'type_label' => $this->getTypeLabel($movement->type),
                    'quantity' => $movement->quantity,
                    'quantity_display' => ($movement->type === 'entry' || $movement->type === 'return' ? '+' : '-') . $movement->quantity,
                    'reason' => $movement->reason,
                    'product_name' => $productName . $variantInfo,
                    'user' => $movement->user ? [
                        'id' => $movement->user->id,
                        'name' => $movement->user->name,
                    ] : null,
                    'created_at' => $movement->created_at->format('d/m/Y H:i'),
                    'created_at_raw' => $movement->created_at,
                ];
            }),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    /**
     * Get list of users for filter dropdown.
     */
    public function users(): JsonResponse
    {
        $users = User::select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $users->map(fn ($user) => [
                'value' => $user->id,
                'label' => $user->name,
            ]),
        ]);
    }

    /**
     * Get type label in Portuguese.
     */
    private function getTypeLabel(string $type): string
    {
        return match ($type) {
            'entry' => 'Entrada',
            'exit' => 'Saída',
            'adjustment' => 'Ajuste',
            'return' => 'Devolução',
            default => $type,
        };
    }
}
