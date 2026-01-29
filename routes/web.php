<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * SISCONDI - Rotas Web (SPA)
 * Todas as rotas são tratadas pelo Vue Router
 */

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');