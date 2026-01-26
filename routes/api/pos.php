<?php

declare(strict_types=1);

use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('pos/active-sale', [PosController::class, 'activeSale']);
    Route::post('pos/start', [PosController::class, 'start']);
    Route::post('pos/add-item', [PosController::class, 'addItem']);
    Route::post('pos/remove-item', [PosController::class, 'removeItem']);
    Route::post('pos/remove-item-by-code', [PosController::class, 'removeItemByCode']);
    Route::post('pos/identify-customer', [PosController::class, 'identifyCustomer']);
    Route::post('pos/quick-customer', [PosController::class, 'quickRegisterCustomer']);
    Route::post('pos/apply-discount', [PosController::class, 'applyDiscount']);
    Route::post('pos/apply-coupon', [PosController::class, 'applyCoupon']);
    Route::post('pos/remove-coupon', [PosController::class, 'removeCoupon']);
    Route::post('pos/add-payment', [PosController::class, 'addPayment']);
    Route::post('pos/remove-payment', [PosController::class, 'removePayment']);
    Route::post('pos/log-manager-action', [PosController::class, 'logManagerAction']);
    Route::post('pos/cancel', [PosController::class, 'cancel']);
    Route::post('pos/finish', [PosController::class, 'finish']);
});
