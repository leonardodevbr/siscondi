<?php

declare(strict_types=1);

use App\Http\Controllers\ServantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::post('/servants/{servant}/update', [ServantController::class, 'update'])->name('servants.update.post');
    Route::apiResource('servants', ServantController::class);
});
