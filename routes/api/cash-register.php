<?php

declare(strict_types=1);

use App\Http\Controllers\CashRegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('cash-register/status', [CashRegisterController::class, 'status']);
    Route::post('cash-register/open', [CashRegisterController::class, 'open']);
    Route::post('cash-register/{cashRegister}/close', [CashRegisterController::class, 'close']);
    Route::post('cash-register/{cashRegister}/movement', [CashRegisterController::class, 'movement']);
});
