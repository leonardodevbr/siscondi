<?php

declare(strict_types=1);

use App\Http\Controllers\DepartmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/departments/{department}/update', [DepartmentController::class, 'update'])->name('departments.update.post');
    Route::apiResource('departments', DepartmentController::class);
});
