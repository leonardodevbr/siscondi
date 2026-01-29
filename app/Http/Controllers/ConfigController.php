<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;

class ConfigController extends Controller
{
    /**
     * Configurações públicas para o frontend (nome do sistema, dados do município, etc.).
     * Nome do sistema: .env APP_NAME ou settings app_name.
     */
    public function publicConfig(): JsonResponse
    {
        $appName = Settings::get('app_name') ?: Config::get('app.name');

        return response()->json([
            'app_name' => $appName,
            'municipality' => [
                'name' => Settings::get('municipality_name'),
                'state' => Settings::get('municipality_state'),
                'address' => Settings::get('municipality_address'),
                'email' => Settings::get('municipality_email'),
                'cnpj' => Settings::get('municipality_cnpj'),
            ],
        ]);
    }
}
