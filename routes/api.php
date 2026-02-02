<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * SISCONDI - Sistema de Concessão de Diárias
 * API Routes
 */

// Autenticação e Configuração
require __DIR__.'/api/auth.php';
require __DIR__.'/api/users.php';
require __DIR__.'/api/config.php';
require __DIR__.'/api/settings.php';
require __DIR__.'/api/upload.php';

// Estrutura Organizacional (Municípios e Secretarias)
require __DIR__.'/api/municipalities.php';
require __DIR__.'/api/departments.php';

// Módulo de Diárias
require __DIR__.'/api/legislations.php'; // Legislações e Valores
require __DIR__.'/api/positions.php'; // Cargos/Posições (símbolo + pivot com itens da lei)
require __DIR__.'/api/servants.php'; // Servidores Públicos
require __DIR__.'/api/daily-requests.php'; // Solicitações de Diárias

// Notificações Push (Web Push / VAPID)
use App\Http\Controllers\PushSubscriptionController;
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store']);
    Route::post('/push/test', [PushSubscriptionController::class, 'test']);
});

// Dashboard
require __DIR__.'/api/dashboard.php';