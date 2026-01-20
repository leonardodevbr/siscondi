<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductStockMovementController extends Controller
{
    /**
     * Display stock movements for a specific product (through its variants).
     */
    public function __invoke(Request $request, Product $product): JsonResponse
    {
        $this->authorize('stock.view');

        $variantIds = $product->variants()->pluck('id');

        $movements = StockMovement::query()
            ->whereIn('product_variant_id', $variantIds)
            ->with(['user', 'productVariant', 'branch'])
            ->latest()
            ->paginate(15);

        return StockMovementResource::collection($movements)->response();
    }
}
