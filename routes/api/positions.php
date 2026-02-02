<?php

declare(strict_types=1);

use App\Http\Controllers\PositionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/positions/{position}/update', [PositionController::class, 'update'])->name('positions.update.post');
    Route::apiResource('positions', PositionController::class);
});
