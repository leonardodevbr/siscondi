<?php

declare(strict_types=1);

use App\Http\Controllers\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('expenses', ExpenseController::class);
    Route::post('/expenses/{expense}/pay', [ExpenseController::class, 'pay']);
});
