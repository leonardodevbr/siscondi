<?php

declare(strict_types=1);

use App\Http\Controllers\DailyRequestController;
use Illuminate\Support\Facades\Route;

/**
 * Rota temporária: pré-visualização do PDF em HTML para validação do layout.
 * GET /pdf-preview/daily-request/{id}
 */
Route::get('/pdf-preview/daily-request/{id}', [DailyRequestController::class, 'pdfPreview'])
    ->name('pdf-preview.daily-request');

/**
 * SISCONDI - Rotas Web (SPA)
 * Todas as demais rotas são tratadas pelo Vue Router
 */
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');