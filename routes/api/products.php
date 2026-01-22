<?php

declare(strict_types=1);

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('products', ProductController::class);
    Route::get('products/{product}/availability', [ProductController::class, 'checkAvailability']);

    Route::get('products/import/template', [ProductImportController::class, 'template']);
    Route::post('products/import', [ProductImportController::class, 'store']);

    Route::post('inventory/adjustment', [InventoryController::class, 'storeAdjustment']);
    Route::get('inventory/scan', [InventoryController::class, 'scan']);
    Route::get('inventory/{product}/history', [InventoryController::class, 'history']);
    Route::get('inventory/movements', [InventoryController::class, 'index']);
    Route::get('inventory/users', [InventoryController::class, 'users']);
});
