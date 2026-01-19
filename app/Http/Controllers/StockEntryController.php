<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Stock\CreateStockEntryAction;
use App\Http\Requests\StoreStockEntryRequest;
use Illuminate\Http\JsonResponse;

class StockEntryController extends Controller
{
    public function __construct(
        private readonly CreateStockEntryAction $createStockEntryAction
    ) {
    }

    /**
     * Store a newly created stock entry.
     */
    public function store(StoreStockEntryRequest $request): JsonResponse
    {
        try {
            $this->createStockEntryAction->execute(
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'message' => 'Stock entry created successfully',
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
