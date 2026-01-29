<?php

declare(strict_types=1);

use App\Http\Controllers\LegislationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/legislations/destination-types', [LegislationController::class, 'destinationTypes']);
    Route::apiResource('legislations', LegislationController::class);
});
