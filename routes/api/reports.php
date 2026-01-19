<?php

declare(strict_types=1);

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/reports/sales', [ReportController::class, 'sales']);
    Route::get('/reports/sales/export', [ReportController::class, 'exportSales']);
    Route::get('/reports/stock', [ReportController::class, 'stock']);
    Route::get('/reports/stock/export', [ReportController::class, 'exportStock']);
});
