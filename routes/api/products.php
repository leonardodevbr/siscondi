<?php

declare(strict_types=1);

use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('products', ProductController::class);
    
    Route::get('products/import/template', [ProductImportController::class, 'template']);
    Route::post('products/import', [ProductImportController::class, 'store']);
});
