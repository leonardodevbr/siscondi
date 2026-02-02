<?php

declare(strict_types=1);

use App\Http\Controllers\DailyRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('daily-requests/pending-signatures', [DailyRequestController::class, 'pendingSignatures']);
    Route::get('daily-requests/{daily_request}/pdf', [DailyRequestController::class, 'pdf'])->name('daily-requests.pdf');
    Route::get('daily-requests/{daily_request}/timeline', [DailyRequestController::class, 'timeline'])->name('daily-requests.timeline');
    Route::post('daily-requests/{daily_request}/update', [DailyRequestController::class, 'update'])->name('daily-requests.update.post');
    Route::apiResource('daily-requests', DailyRequestController::class);

    // Ações específicas do fluxo de aprovação
    Route::post('daily-requests/{dailyRequest}/validate', [DailyRequestController::class, 'validate']);
    Route::post('daily-requests/{dailyRequest}/authorize', [DailyRequestController::class, 'authorizeRequest']);
    Route::post('daily-requests/{dailyRequest}/pay', [DailyRequestController::class, 'pay']);
    Route::post('daily-requests/{dailyRequest}/cancel', [DailyRequestController::class, 'cancel']);
});
