<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MercadoPagoConnectRequest;
use App\Http\Requests\UpdateSettingRequest;
use App\Models\Setting;
use App\Services\Payment\MercadoPagoPointService;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    public function __construct(
        private readonly MercadoPagoPointService $mercadoPagoPointService
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('settings.manage');

        $settings = Setting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group')
            ->map(function ($groupSettings) {
                return $groupSettings->map(function (Setting $setting): array {
                    $value = $setting->getTypedValue();
                    $isMasked = in_array($setting->key, Setting::MASKED_KEYS, true)
                        && $value !== null
                        && $value !== '';

                    return [
                        'key' => $setting->key,
                        'value' => $isMasked ? null : $value,
                        'type' => $setting->type,
                        'masked' => $isMasked,
                    ];
                })->values();
            });

        return response()->json($settings);
    }

    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $data = $request->validated();

        foreach ($data['settings'] as $item) {
            $key = $item['key'] ?? null;
            if (! is_string($key)) {
                continue;
            }
            if (in_array($key, Setting::MASKED_KEYS, true)) {
                $v = $item['value'] ?? null;
                if ($v === null || $v === '') {
                    continue;
                }
            }

            /** @var Setting $setting */
            $setting = Setting::query()->firstOrNew(['key' => $key]);

            if (isset($item['group'])) {
                $setting->group = $item['group'];
            } elseif (! $setting->exists) {
                $setting->group = 'general';
            }

            if (isset($item['type'])) {
                $setting->type = $item['type'];
            } elseif (! $setting->exists) {
                $setting->type = 'string';
            }

            $setting->setTypedValue($item['value'] ?? null);
            $setting->save();
        }

        Cache::forget('settings.all');

        return response()->json(['message' => 'Settings updated successfully.']);
    }

    /**
     * Conecta ao Mercado Pago com Client ID e Client Secret, gera e persiste o Access Token.
     */
    public function mercadopagoConnect(MercadoPagoConnectRequest $request): JsonResponse
    {
        $clientId = $request->validated('mp_client_id');
        $clientSecret = $request->validated('mp_client_secret');

        try {
            $this->mercadoPagoPointService->authenticate($clientId, $clientSecret);
        } catch (\RuntimeException $e) {
            return response()->json(
                ['message' => 'Credenciais inválidas. Verifique o Client ID e o Client Secret.'],
                422
            );
        }

        Settings::set('mp_client_id', $clientId, 'string', 'integrations');
        Settings::set('mp_client_secret', $clientSecret, 'string', 'integrations');
        Cache::forget('settings.all');

        return response()->json(['message' => 'Conectado ao Mercado Pago. Token gerado com sucesso.']);
    }

    /**
     * Retorna se a integração Mercado Pago está conectada (possui access token).
     */
    public function mercadopagoStatus(): JsonResponse
    {
        $token = $this->mercadoPagoPointService->getAccessToken();

        return response()->json(['connected' => is_string($token) && $token !== '']);
    }
}

