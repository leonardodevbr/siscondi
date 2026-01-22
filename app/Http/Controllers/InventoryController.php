<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
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
        $user = auth()->user();
        $branchId = $user?->branch_id ?? request()->header('X-Branch-ID');
        
        if (! $branchId) {
            return response()->json([
                'message' => 'Filial não identificada. Não é possível realizar a movimentação.',
            ], 422);
        }

        $branchId = (int) $branchId;

        $productId = $request->product_id;
        
        if ($request->variation_id && ! $productId) {
            $variant = ProductVariant::findOrFail($request->variation_id);
            $productId = $variant->product_id;
        }
        
        if (! $productId) {
            return response()->json([
                'message' => 'Produto não identificado. É necessário informar product_id ou variation_id.',
            ], 422);
        }

        $product = Product::with(['variants.inventories' => function ($query) use ($branchId): void {
            $query->where('branch_id', $branchId);
        }])->findOrFail($productId);

        $variant = null;
        $inventory = null;
        $currentStock = 0;

        if ($request->variation_id) {
            $variant = $product->variants()->find($request->variation_id);
            if (! $variant) {
                return response()->json([
                    'message' => 'Variação não encontrada.',
                ], 404);
            }
            $inventory = $variant->inventories->first();
            $currentStock = $inventory?->quantity ?? 0;
        } else {
            $variant = $product->variants()->first();
            if (! $variant) {
                return response()->json([
                    'message' => 'Produto sem variação cadastrada.',
                ], 422);
            }
            $inventory = $variant->inventories->first();
            $currentStock = $inventory?->quantity ?? 0;
        }

        $quantity = abs((int) $request->quantity);
        $finalType = $request->type;
        $operation = $request->operation ?? $this->getDefaultOperation($request->type);

        if ($operation === 'sub' && $currentStock < $quantity) {
            return response()->json([
                'message' => "Saldo insuficiente para realizar esta baixa. Estoque atual: {$currentStock}",
            ], 422);
        }

        $movement = InventoryMovement::create([
            'product_id' => $productId,
            'variation_id' => $request->variation_id,
            'user_id' => auth()->id(),
            'branch_id' => $branchId,
            'type' => $finalType,
            'operation' => $operation,
            'quantity' => $quantity,
            'reason' => $request->reason,
        ]);

        $product->refresh();
        $product->load(['variants.inventories' => function ($query) use ($branchId): void {
            $query->where('branch_id', $branchId);
        }]);

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
                'operation' => $movement->operation,
                'quantity' => $movement->quantity,
                'reason' => $movement->reason,
                'created_at' => $movement->created_at,
            ],
            'current_stock' => $currentStock,
        ], 201);
    }

    /**
     * Get default operation based on movement type.
     */
    private function getDefaultOperation(string $type): string
    {
        return match ($type) {
            'entry', 'return' => 'add',
            'exit' => 'sub',
            'adjustment' => 'sub',
            default => 'add',
        };
    }

    /**
     * Get inventory movement history for a product.
     */
    public function history(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'variation_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $user = auth()->user();
        $branchId = $user?->branch_id ?? $request->header('X-Branch-ID');
        $query = InventoryMovement::with(['user:id,name', 'variation:id,sku,attributes'])
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc');

        if ($branchId) {
            $query->where('branch_id', (int) $branchId);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        }

        if ($request->has('variation_id') && $request->variation_id) {
            $query->where('variation_id', $request->variation_id);
        }

        $movements = $query->paginate(20);

        return response()->json([
            'data' => $movements->map(function ($movement) {
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
                    $variantInfo = count($variantParts) > 0 
                        ? implode('/', $variantParts) 
                        : ($movement->variation->sku ?? '');
                }

                $op = $movement->operation ?? 'add';
                return [
                    'id' => $movement->id,
                    'type' => $movement->type,
                    'type_label' => $this->getTypeLabel($movement->type),
                    'operation' => $op,
                    'quantity' => $movement->quantity,
                    'quantity_display' => ($op === 'sub' ? '-' : '+') . $movement->quantity,
                    'reason' => $movement->reason,
                    'user' => $movement->user ? [
                        'id' => $movement->user->id,
                        'name' => $movement->user->name,
                    ] : null,
                    'variation' => $movement->variation ? [
                        'id' => $movement->variation->id,
                        'sku' => $movement->variation->sku,
                        'barcode' => $movement->variation->barcode,
                        'info' => $variantInfo,
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
        $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $user = auth()->user();
        $userBranchId = $user?->branch_id ? (int) $user->branch_id : null;

        $query = InventoryMovement::with([
            'user:id,name',
            'product:id,name',
            'variation:id,sku,barcode,attributes',
            'branch:id,name',
        ])->orderBy('created_at', 'desc');

        if ($userBranchId !== null) {
            $query->where('branch_id', $userBranchId);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search): void {
                $q->whereHas('product', function ($q2) use ($search): void {
                    $q2->where('name', 'like', "%{$search}%");
                })
                    ->orWhere(function ($q2) use ($search): void {
                        $q2->whereNotNull('variation_id')
                            ->whereHas('variation', function ($q3) use ($search): void {
                                $q3->where('sku', 'like', "%{$search}%");
                            });
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

                $op = $movement->operation ?? 'add';
                return [
                    'id' => $movement->id,
                    'type' => $movement->type,
                    'type_label' => $this->getTypeLabel($movement->type),
                    'operation' => $op,
                    'quantity' => $movement->quantity,
                    'quantity_display' => ($op === 'sub' ? '-' : '+') . $movement->quantity,
                    'reason' => $movement->reason,
                    'product_name' => $productName . $variantInfo,
                    'branch' => $movement->branch ? [
                        'id' => $movement->branch->id,
                        'name' => $movement->branch->name,
                    ] : null,
                    'branch_name' => $movement->branch?->name ?? 'N/A',
                    'user' => $movement->user ? [
                        'id' => $movement->user->id,
                        'name' => $movement->user->name,
                    ] : null,
                    'variation' => $movement->variation ? [
                        'id' => $movement->variation->id,
                        'sku' => $movement->variation->sku,
                        'barcode' => $movement->variation->barcode,
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

    /**
     * Search for a product or variation by SKU or barcode.
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $code = trim((string) $request->code);
        $branchId = auth()->user()?->branch_id ?? request()->header('X-Branch-ID');

        if (! $branchId) {
            return response()->json([
                'message' => 'Filial não identificada. Não é possível realizar a busca.',
            ], 422);
        }

        $branchId = (int) $branchId;

        $variation = ProductVariant::where('sku', $code)
            ->orWhere('barcode', $code)
            ->first();

        if ($variation) {
            $product = $variation->product;
            $inventory = $variation->inventories()->where('branch_id', $branchId)->first();
            $attrs = $variation->attributes ?? [];
            $attrString = implode(' / ', array_values(array_filter($attrs)));
            $name = $product->name . ($attrString ? ' - ' . $attrString : '');

            return response()->json([
                'product_id' => $product->id,
                'variation_id' => $variation->id,
                'name' => $name,
                'current_stock' => $inventory?->quantity ?? 0,
            ]);
        }

        $product = Product::where('sku', $code)
            ->orWhere('barcode', $code)
            ->first();

        if ($product) {
            // If it's a simple product (no variations or only one default variation)
            // Or we need to select one if no variation was provided from the scan
            $defaultVariation = $product->variants()->first();

            if (! $defaultVariation) {
                return response()->json([
                    'message' => 'Produto encontrado, mas sem variações para ajuste.',
                ], 404);
            }

            $inventory = $defaultVariation->inventories()->where('branch_id', $branchId)->first();
            $attrs = $defaultVariation->attributes ?? [];
            $attrString = implode(' / ', array_values(array_filter($attrs)));
            $name = $product->name . ($attrString ? ' - ' . $attrString : '');

            return response()->json([
                'product_id' => $product->id,
                'variation_id' => $defaultVariation->id,
                'name' => $name,
                'current_stock' => $inventory?->quantity ?? 0,
            ]);
        }

        return response()->json([
            'message' => 'Produto não encontrado.',
        ], 404);
    }
}
