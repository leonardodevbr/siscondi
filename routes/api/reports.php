<?php

declare(strict_types=1);

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {
    // Relatórios - apenas admin ou superior
    Route::get('/reports/daily-requests', [ReportController::class, 'dailyRequestsReport'])->name('reports.daily-requests');
    Route::get('/reports/daily-requests/export/csv', [ReportController::class, 'exportDailyRequestsCsv'])->name('reports.daily-requests.csv');
    Route::get('/reports/daily-requests/export/pdf', [ReportController::class, 'exportDailyRequestsPdf'])->name('reports.daily-requests.pdf');
    
    Route::get('/reports/servants', [ReportController::class, 'servantsReport'])->name('reports.servants');
    Route::get('/reports/servants/export/csv', [ReportController::class, 'exportServantsCsv'])->name('reports.servants.csv');
    Route::get('/reports/servants/export/pdf', [ReportController::class, 'exportServantsPdf'])->name('reports.servants.pdf');
});
