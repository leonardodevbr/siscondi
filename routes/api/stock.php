<?php

declare(strict_types=1);

use App\Http\Controllers\ProductStockMovementController;
use App\Http\Controllers\StockEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/stock/entries', [StockEntryController::class, 'store']);
    Route::get('/products/{product}/movements', ProductStockMovementController::class);
});
