<?php

declare(strict_types=1);

use App\Http\Controllers\PixController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/pix/sales/{sale}/generate', [PixController::class, 'generate']);
});

Route::post('/pix/webhook', [PixController::class, 'webhook']);
