<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CulturalAgentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', DashboardController::class);
    Route::get('/cultural-agents', [CulturalAgentController::class, 'index']);
    Route::post('/cultural-agents', [CulturalAgentController::class, 'store']);
    Route::get('/map/points', [MapController::class, 'points']);
    Route::post('/sync/batch', [SyncController::class, 'batch']);
});
