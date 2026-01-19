<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductStockMovementController extends Controller
{
    /**
     * Display stock movements for a specific product.
     */
    public function __invoke(Request $request, Product $product): JsonResponse
    {
        $this->authorize('stock.view');

        $movements = $product->stockMovements()
            ->with(['user'])
            ->latest()
            ->paginate(15);

        return StockMovementResource::collection($movements)->response();
    }
}
