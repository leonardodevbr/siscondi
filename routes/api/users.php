<?php

declare(strict_types=1);

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('user/push-subscription', [UserController::class, 'storePushSubscription'])->name('user.push-subscription');
    Route::apiResource('users', UserController::class);
});
