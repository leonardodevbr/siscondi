<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
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
        $stockMovementType = $this->mapTypeToEnum($request->type, $request->operation);

        // Valida estoque apenas para operações de saída/perda
        if (in_array($stockMovementType, [StockMovementType::LOSS, StockMovementType::ADJUSTMENT], true)) {
            // ADJUSTMENT com quantidade negativa significa subtração
            if ($currentStock < $quantity) {
                return response()->json([
                    'message' => "Saldo insuficiente para realizar esta baixa. Estoque atual: {$currentStock}",
                ], 422);
            }
        }

        // Cria StockMovement (tabela única e moderna)
        $movement = StockMovement::create([
            'product_variant_id' => $variant->id,
            'user_id' => auth()->id(),
            'branch_id' => $branchId,
            'type' => $stockMovementType,
            'quantity' => $quantity,
            'reason' => $request->reason ?? 'Ajuste manual de estoque',
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
                'type' => $movement->type->value,
                'type_label' => $this->getTypeLabel($movement->type->value),
                'quantity' => $movement->quantity,
                'reason' => $movement->reason,
                'created_at' => $movement->created_at,
            ],
            'current_stock' => $currentStock,
        ], 201);
    }

    /**
     * Mapeia o type/operation legado para o novo Enum StockMovementType.
     */
    private function mapTypeToEnum(string $type, ?string $operation = null): StockMovementType
    {
        return match ($type) {
            'entry' => StockMovementType::ENTRY,
            'return' => StockMovementType::RETURN,
            'exit' => StockMovementType::LOSS,
            'adjustment' => StockMovementType::ADJUSTMENT,
            default => StockMovementType::ADJUSTMENT,
        };
    }

    /**
     * Get stock movement history for a product.
     */
    public function history(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'variation_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $user = auth()->user();
        $branchId = $user?->branch_id ?? $request->header('X-Branch-ID');
        
        // Busca variantes do produto
        $variantIds = ProductVariant::where('product_id', $productId)->pluck('id');
        
        $query = StockMovement::with(['user:id,name', 'productVariant:id,sku,barcode,attributes'])
            ->whereIn('product_variant_id', $variantIds)
            ->orderBy('created_at', 'desc');

        if ($branchId) {
            $query->where('branch_id', (int) $branchId);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        }

        if ($request->has('variation_id') && $request->variation_id) {
            $query->where('product_variant_id', $request->variation_id);
        }

        $movements = $query->paginate(20);

        return response()->json([
            'data' => $movements->map(function ($movement) {
                $variantInfo = '';
                if ($movement->productVariant) {
                    $attrs = $movement->productVariant->attributes ?? [];
                    $variantParts = [];
                    if (isset($attrs['cor'])) {
                        $variantParts[] = $attrs['cor'];
                    }
                    if (isset($attrs['tamanho'])) {
                        $variantParts[] = $attrs['tamanho'];
                    }
                    $variantInfo = count($variantParts) > 0 
                        ? implode('/', $variantParts) 
                        : ($movement->productVariant->sku ?? '');
                }

                $isNegative = in_array($movement->type, [StockMovementType::SALE, StockMovementType::LOSS], true);
                
                return [
                    'id' => $movement->id,
                    'type' => $movement->type->value,
                    'type_label' => $this->getTypeLabel($movement->type->value),
                    'quantity' => $movement->quantity,
                    'quantity_display' => ($isNegative ? '-' : '+') . $movement->quantity,
                    'reason' => $movement->reason,
                    'user' => $movement->user ? [
                        'id' => $movement->user->id,
                        'name' => $movement->user->name,
                    ] : null,
                    'variation' => $movement->productVariant ? [
                        'id' => $movement->productVariant->id,
                        'sku' => $movement->productVariant->sku,
                        'barcode' => $movement->productVariant->barcode,
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
     * Get all stock movements with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $user = auth()->user();
        $userBranchId = $user?->branch_id ? (int) $user->branch_id : null;

        $query = StockMovement::with([
            'user:id,name',
            'productVariant.product:id,name',
            'productVariant:id,product_id,sku,barcode,attributes',
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
                $q->whereHas('productVariant.product', function ($q2) use ($search): void {
                    $q2->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('productVariant', function ($q2) use ($search): void {
                    $q2->where('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
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
                $productName = $movement->productVariant?->product?->name ?? 'N/A';
                $variantInfo = '';
                
                if ($movement->productVariant) {
                    $attrs = $movement->productVariant->attributes ?? [];
                    $variantParts = [];
                    if (isset($attrs['cor'])) {
                        $variantParts[] = $attrs['cor'];
                    }
                    if (isset($attrs['tamanho'])) {
                        $variantParts[] = $attrs['tamanho'];
                    }
                    $variantInfo = count($variantParts) > 0 ? ' - ' . implode('/', $variantParts) : ' - ' . ($movement->productVariant->sku ?? '');
                }

                $isNegative = in_array($movement->type, [StockMovementType::SALE, StockMovementType::LOSS], true);
                
                return [
                    'id' => $movement->id,
                    'type' => $movement->type->value,
                    'type_label' => $this->getTypeLabel($movement->type->value),
                    'quantity' => $movement->quantity,
                    'quantity_display' => ($isNegative ? '-' : '+') . $movement->quantity,
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
                    'variation' => $movement->productVariant ? [
                        'id' => $movement->productVariant->id,
                        'sku' => $movement->productVariant->sku,
                        'barcode' => $movement->productVariant->barcode,
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
     * Users with branch: only same branch. Super Admin: all, or filter by request branch_id.
     */
    public function users(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $user = auth()->user();
        $userBranchId = $user?->branch_id ?? $user?->branch?->id;
        $userBranchId = $userBranchId ? (int) $userBranchId : null;

        $query = User::select('id', 'name')->orderBy('name');

        if ($userBranchId !== null) {
            $query->where('branch_id', $userBranchId);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        }

        $users = $query->get();

        return response()->json([
            'data' => $users->map(fn ($u) => [
                'value' => $u->id,
                'label' => $u->name,
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
            'sale' => 'Venda',
            'return' => 'Devolução',
            'loss' => 'Perda',
            'adjustment' => 'Ajuste',
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
            $qty = $inventory?->quantity ?? 0;

            if ($qty < 1) {
                return response()->json([
                    'message' => 'Sem estoque nesta filial.',
                ], 422);
            }

            $attrs = $variation->attributes ?? [];
            $attrString = implode(' / ', array_values(array_filter($attrs)));
            $name = $product->name . ($attrString ? ' - ' . $attrString : '');

            return response()->json([
                'product_id' => $product->id,
                'variation_id' => $variation->id,
                'name' => $name,
                'current_stock' => $qty,
                'price' => $variation->getEffectivePrice(),
                'variation_attributes' => $attrs,
                'image' => $variation->image ? asset('storage/' . $variation->image) : ($product->image ? asset('storage/' . $product->image) : null),
            ]);
        }

        $product = Product::whereHas('variants', function ($q) use ($code): void {
            $q->where('sku', $code)->orWhere('barcode', $code);
        })->with(['variants' => function ($q) use ($code): void {
            $q->where('sku', $code)->orWhere('barcode', $code);
        }])->first();

        if ($product) {
            $defaultVariation = $product->variants->first();

            if (! $defaultVariation) {
                return response()->json([
                    'message' => 'Produto encontrado, mas sem variações para ajuste.',
                ], 404);
            }

            $inventory = $defaultVariation->inventories()->where('branch_id', $branchId)->first();
            $qty = $inventory?->quantity ?? 0;

            if ($qty < 1) {
                return response()->json([
                    'message' => 'Sem estoque nesta filial.',
                ], 422);
            }

            $attrs = $defaultVariation->attributes ?? [];
            $attrString = implode(' / ', array_values(array_filter($attrs)));
            $name = $product->name . ($attrString ? ' - ' . $attrString : '');

            return response()->json([
                'product_id' => $product->id,
                'variation_id' => $defaultVariation->id,
                'name' => $name,
                'current_stock' => $qty,
                'price' => $defaultVariation->getEffectivePrice(),
                'variation_attributes' => $attrs,
                'image' => $defaultVariation->image ? asset('storage/' . $defaultVariation->image) : ($product->image ? asset('storage/' . $product->image) : null),
            ]);
        }

        return response()->json([
            'message' => 'Produto não cadastrado.',
        ], 404);
    }
}
