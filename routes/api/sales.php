<?php

declare(strict_types=1);

use App\Http\Controllers\PosController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('sales/simulate-installments', [PosController::class, 'simulateInstallments'])->name('sales.simulate-installments');
    Route::get('sales/export', [SaleController::class, 'export'])->name('sales.export');
    Route::apiResource('sales', SaleController::class);
});
