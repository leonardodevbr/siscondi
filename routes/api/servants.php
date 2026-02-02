<?php

declare(strict_types=1);

use App\Http\Controllers\ServantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/servants/import/template', [ServantController::class, 'downloadTemplate'])->name('servants.import.template');
    Route::get('/servants/import/status', [ServantController::class, 'importStatus'])->name('servants.import.status');
    Route::delete('/servants/import/status', [ServantController::class, 'clearImportStatus'])->name('servants.import.status.clear');
    Route::post('/servants/import/validate', [ServantController::class, 'validateImport'])->name('servants.import.validate');
    Route::post('/servants/import', [ServantController::class, 'import'])->name('servants.import');
    Route::post('/servants/{servant}/update', [ServantController::class, 'update'])->name('servants.update.post');
    Route::apiResource('servants', ServantController::class);
});
