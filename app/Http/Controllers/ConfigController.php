<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Settings;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    /**
     * Retorna configurações públicas (feature flags) acessíveis por qualquer usuário autenticado.
     * Inclui active_payment_gateway para o PDV decidir fluxo cartão (maquininha vs manual).
     */
    public function publicConfig(): JsonResponse
    {
        $token = Settings::get('mp_access_token');
        $clientId = Settings::get('mp_client_id');
        $hasMpPoint = (is_string($token) && $token !== '')
            || (is_string($clientId) && $clientId !== '');
        $activePaymentGateway = $hasMpPoint ? 'mercadopago_point' : 'manual';

        return response()->json([
            'enable_global_stock_search' => Settings::get('enable_global_stock_search', false),
            'sku_auto_generation' => Settings::get('sku_auto_generation', true),
            'sku_pattern' => Settings::get('sku_pattern', '{NAME}-{VARIANTS}-{SEQ}'),
            'active_payment_gateway' => $activePaymentGateway,
        ]);
    }
}
