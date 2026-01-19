<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Sales\CreateSaleAction;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    public function __construct(
        private readonly CreateSaleAction $createSaleAction
    ) {
    }

    /**
     * Store a newly created sale.
     */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        try {
            $sale = $this->createSaleAction->execute(
                $request->validated(),
                $request->user()
            );

            return response()->json(new SaleResource($sale), 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
