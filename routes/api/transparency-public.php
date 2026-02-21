<?php

declare(strict_types=1);

use App\Http\Controllers\Public\TransparencyController;
use Illuminate\Support\Facades\Route;

/**
 * Portal da Transparência - API pública (sem autenticação)
 */
Route::prefix('public/transparency')->name('public.transparency.')->group(function (): void {
    Route::get('/config', [TransparencyController::class, 'config'])->name('config');
    Route::get('/daily-allowances', [TransparencyController::class, 'dailyAllowances'])->name('daily-allowances');
    Route::get('/daily-allowances/export/pdf', [TransparencyController::class, 'exportPdf'])->name('daily-allowances.export-pdf');
    Route::get('/daily-allowances/{id}', [TransparencyController::class, 'show'])->name('daily-allowances.show');
    Route::get('/destinations', [TransparencyController::class, 'destinations'])->name('destinations');
    Route::get('/servants', [TransparencyController::class, 'servants'])->name('servants');
    Route::get('/municipalities', [TransparencyController::class, 'municipalities'])->name('municipalities');
});
