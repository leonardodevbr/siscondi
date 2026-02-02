<?php

declare(strict_types=1);

use App\Http\Controllers\MunicipalityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/municipalities/current', [MunicipalityController::class, 'current']);
    Route::put('/municipalities/current', [MunicipalityController::class, 'updateCurrent']);
    Route::post('/municipalities/current/update', [MunicipalityController::class, 'updateCurrent'])->name('municipalities.current.update.post');
    Route::get('/municipalities/{id}/departments', [MunicipalityController::class, 'departments']);

    Route::get('/municipalities', [MunicipalityController::class, 'index']);
    Route::get('/municipalities/{id}', [MunicipalityController::class, 'show']);
    Route::put('/municipalities/{id}', [MunicipalityController::class, 'update']);
    Route::post('/municipalities/{id}/update', [MunicipalityController::class, 'update'])->name('municipalities.update.post');
});
