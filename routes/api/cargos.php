<?php

declare(strict_types=1);

use App\Http\Controllers\CargoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('cargos', CargoController::class);
});
