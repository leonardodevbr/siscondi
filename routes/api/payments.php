<?php

declare(strict_types=1);

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/**
 * Rotas unificadas de pagamento - agnósticas ao gateway.
 */
Route::middleware('auth:sanctum')->prefix('payments')->name('payments.')->group(function (): void {
    // Simular parcelas para cartão de crédito
    Route::get('simulate-installments', [PaymentController::class, 'simulateInstallments'])
        ->name('simulate-installments');

    // Processar pagamento (PIX, cartão, etc)
    Route::post('process', [PaymentController::class, 'process'])
        ->name('process');
});

/**
 * Webhooks - públicos, sem autenticação.
 */
Route::prefix('webhooks')->name('webhooks.')->group(function (): void {
    Route::post('{gateway}', [PaymentController::class, 'webhook'])
        ->name('gateway')
        ->where('gateway', 'mercadopago|pagbank');
});
