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
require __DIR__.'/api/legislations.php'; // Cargos e Valores
require __DIR__.'/api/servants.php'; // Servidores Públicos
require __DIR__.'/api/daily-requests.php'; // Solicitações de Diárias

// Dashboard
require __DIR__.'/api/dashboard.php';