<?php

declare(strict_types=1);

use App\Http\Controllers\CouponController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('coupons', CouponController::class);
});
