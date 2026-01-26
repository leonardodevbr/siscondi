<?php

declare(strict_types=1);

use App\Http\Controllers\MercadoPagoPointController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('mp-point')->name('mp-point.')->group(function (): void {
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('devices', [MercadoPagoPointController::class, 'indexDevices'])->name('devices');
        Route::post('store', [MercadoPagoPointController::class, 'store'])->name('store');
        Route::post('process-payment', [MercadoPagoPointController::class, 'processPayment'])->name('process-payment');
        Route::get('check-status/{intentId}', [MercadoPagoPointController::class, 'checkStatus'])->name('check-status');
        Route::post('cancel-intent', [MercadoPagoPointController::class, 'cancelIntent'])->name('cancel-intent');
    });
});

Route::prefix('webhook')->name('webhook.')->group(function (): void {
    Route::post('mercadopago-point', [WebhookController::class, 'handleMercadoPagoPoint'])
        ->name('mercadopago-point')
        ->withoutMiddleware(['auth:sanctum']);
});
