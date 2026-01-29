<?php

declare(strict_types=1);

use App\Http\Controllers\DailyRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('daily-requests/{daily_request}/pdf', [DailyRequestController::class, 'pdf'])->name('daily-requests.pdf');
    Route::apiResource('daily-requests', DailyRequestController::class);

    // Ações específicas do fluxo de aprovação
    Route::post('daily-requests/{dailyRequest}/validate', [DailyRequestController::class, 'validate']);
    Route::post('daily-requests/{dailyRequest}/authorize', [DailyRequestController::class, 'authorize']);
    Route::post('daily-requests/{dailyRequest}/pay', [DailyRequestController::class, 'pay']);
    Route::post('daily-requests/{dailyRequest}/cancel', [DailyRequestController::class, 'cancel']);
});
