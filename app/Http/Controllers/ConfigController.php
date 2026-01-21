<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Settings;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    /**
     * Retorna configurações públicas (feature flags) acessíveis por qualquer usuário autenticado.
     */
    public function publicConfig(): JsonResponse
    {
        return response()->json([
            'enable_global_stock_search' => Settings::get('enable_global_stock_search', false),
            'sku_auto_generation' => Settings::get('sku_auto_generation', true),
            'sku_pattern' => Settings::get('sku_pattern', '{NAME}-{VARIANTS}-{SEQ}'),
        ]);
    }
}
