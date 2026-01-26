<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Sale;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoPointService
{
    private const BASE_URL = 'https://api.mercadopago.com';

    /** Margem em segundos para renovar antes de expirar (7 dias). */
    private const REFRESH_MARGIN_SECONDS = 604800;

    /**
     * Token lido do banco (tabela settings). Nunca .env.
     */
    public function getAccessToken(): ?string
    {
        $token = Setting::get('mp_access_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Autentica com Client ID e Client Secret e persiste access_token + expires.
     * Ref.: https://www.mercadopago.com.br/developers/pt/reference/oauth/_oauth_token/post
     *
     * @return array{access_token: string, expires_in: int}
     * @throws \RuntimeException credenciais inválidas ou falha na API
     */
    public function authenticate(string $clientId, string $clientSecret): array
    {
        $url = self::BASE_URL . '/oauth/token';
        $payload = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
        ];

        Log::info('MercadoPagoPointService::authenticate - Request', ['url' => $url]);

        $response = Http::timeout(15)
            ->asJson()
            ->post($url, $payload);

        $body = $response->json();
        Log::info('MercadoPagoPointService::authenticate - Response', [
            'status' => $response->status(),
            'has_access_token' => isset($body['access_token']),
        ]);

        if ($response->failed()) {
            Log::warning('MercadoPagoPointService::authenticate - HTTP error', [
                'status' => $response->status(),
                'body' => $body,
            ]);
            throw new \RuntimeException(
                $this->extractErrorMessage($body, $response->status(), 'Credenciais inválidas. Verifique o Client ID e o Client Secret em Ajustes > Integrações.')
            );
        }

        $accessToken = $body['access_token'] ?? null;
        $expiresIn = (int) ($body['expires_in'] ?? 15552000);

        if (! is_string($accessToken) || $accessToken === '') {
            throw new \RuntimeException('Resposta inválida do Mercado Pago (sem access_token).');
        }

        $expiresAt = time() + $expiresIn;
        Settings::set('mp_access_token', $accessToken, 'string', 'integrations');
        Settings::set('mp_token_expires_at', $expiresAt, 'integer', 'integrations');

        Log::info('MercadoPagoPointService::authenticate - Token salvo', [
            'expires_in_days' => (int) round($expiresIn / 86400),
        ]);

        return ['access_token' => $accessToken, 'expires_in' => $expiresIn];
    }

    /**
     * Renova o token usando Client ID/Secret salvos (útil para cron ou quando expira em 180 dias).
     */
    public function refreshToken(): bool
    {
        $clientId = Setting::get('mp_client_id');
        $clientSecret = Setting::get('mp_client_secret');
        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            Log::info('MercadoPagoPointService::refreshToken - Client ID/Secret não configurados');

            return false;
        }

        try {
            $this->authenticate($clientId, $clientSecret);

            return true;
        } catch (\Throwable $e) {
            Log::warning('MercadoPagoPointService::refreshToken - Falha', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @throws \RuntimeException
     */
    private function ensureTokenConfigured(): string
    {
        $token = $this->getAccessToken();
        $expiresAt = (int) (Setting::get('mp_token_expires_at') ?? 0);

        if ($token && $expiresAt > 0 && time() < $expiresAt - self::REFRESH_MARGIN_SECONDS) {
            return $token;
        }

        if ($token && $expiresAt > 0 && time() < $expiresAt) {
            Log::info('MercadoPagoPointService - Token próximo do vencimento, renovando');
            if ($this->refreshToken()) {
                $token = $this->getAccessToken();
                if ($token) {
                    return $token;
                }
            }
        }

        if (! $token) {
            if ($this->refreshToken()) {
                $token = $this->getAccessToken();
                if ($token) {
                    return $token;
                }
            }
            Log::info('MercadoPagoPointService - mp_access_token vazio e renovação indisponível');
            throw new \RuntimeException('Configuração do Mercado Pago incompleta. Vá em Ajustes > Integrações de Pagamento e use Client ID/Secret para conectar.');
        }

        return $token;
    }

    /**
     * Lista as maquininhas (devices) vinculadas à conta MP.
     *
     * @return array{devices: array<int, array<string, mixed>>, paging: array<string, int>}
     * @throws \RuntimeException quando token ausente ou request falha
     */
    public function getDevices(int $offset = 0, int $limit = 50): array
    {
        $token = $this->ensureTokenConfigured();
        $url = self::BASE_URL . '/point/integration-api/devices';
        Log::info('MercadoPagoPointService::getDevices - Request', ['url' => $url, 'offset' => $offset, 'limit' => $limit]);

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->get($url, ['offset' => $offset, 'limit' => $limit]);

            $body = $response->json();
            Log::info('MercadoPagoPointService::getDevices - Response', [
                'status' => $response->status(),
                'body_keys' => is_array($body) ? array_keys($body) : [],
            ]);

            if ($response->failed()) {
                Log::warning('MercadoPagoPointService::getDevices - HTTP error', [
                    'status' => $response->status(),
                    'body' => $body,
                ]);
                throw new \RuntimeException(
                    $this->extractErrorMessage($body, $response->status(), 'Não foi possível listar as maquininhas. Verifique o token e se a maquininha está ligada e vinculada.')
                );
            }

            return [
                'devices' => $body['devices'] ?? [],
                'paging' => $body['paging'] ?? ['total' => 0, 'limit' => $limit, 'offset' => $offset],
            ];
        } catch (RequestException $e) {
            Log::error('MercadoPagoPointService::getDevices - RequestException', [
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException(
                'Maquininha offline ou indisponível. Verifique a conexão e o token nas configurações.',
                0,
                $e
            );
        }
    }

    /**
     * Cria um Payment Intent e envia para a tela da maquininha.
     * Valor em centavos (ex.: R$ 15,00 = 1500).
     *
     * @return array{id: string, device_id: string, amount: int, ...}
     */
    public function createPaymentIntent(string $deviceId, Sale $sale): array
    {
        $token = $this->ensureTokenConfigured();

        $amountCents = (int) round((float) $sale->final_amount * 100);
        if ($amountCents < 1) {
            throw new \InvalidArgumentException('Valor da venda deve ser maior que zero.');
        }

        $payload = [
            'amount' => $amountCents,
            'description' => "Venda #{$sale->id}",
            'payment' => [
                'installments' => 1,
                'type' => 'credit_card',
            ],
            'additional_info' => [
                'external_reference' => (string) $sale->id,
                'print_on_terminal' => true,
            ],
        ];

        $url = self::BASE_URL . "/point/integration-api/devices/{$deviceId}/payment-intents";
        Log::info('MercadoPagoPointService::createPaymentIntent - Request', [
            'url' => $url,
            'sale_id' => $sale->id,
            'amount_cents' => $amountCents,
        ]);

        try {
            $response = Http::withToken($token)
                ->timeout(20)
                ->asJson()
                ->post($url, $payload);

            $body = $response->json();
            Log::info('MercadoPagoPointService::createPaymentIntent - Response', [
                'status' => $response->status(),
                'intent_id' => $body['id'] ?? null,
            ]);

            if ($response->failed()) {
                Log::warning('MercadoPagoPointService::createPaymentIntent - HTTP error', [
                    'status' => $response->status(),
                    'body' => $body,
                ]);
                throw new \RuntimeException(
                    $this->extractErrorMessage($body, $response->status(), 'Não foi possível enviar o pagamento para a maquininha. Verifique se está ligada e vinculada.')
                );
            }

            return is_array($body) ? $body : [];
        } catch (RequestException $e) {
            Log::error('MercadoPagoPointService::createPaymentIntent - RequestException', [
                'sale_id' => $sale->id,
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException(
                'Maquininha offline ou indisponível. Tente novamente.',
                0,
                $e
            );
        }
    }

    /**
     * Cancela um Payment Intent (apenas se ainda estiver OPEN e não no terminal).
     */
    public function cancelPaymentIntent(string $deviceId, string $intentId): void
    {
        $token = $this->ensureTokenConfigured();

        $url = self::BASE_URL . "/point/integration-api/devices/{$deviceId}/payment-intents/{$intentId}";
        Log::info('MercadoPagoPointService::cancelPaymentIntent - Request', ['intent_id' => $intentId]);

        $response = Http::withToken($token)
            ->timeout(10)
            ->delete($url);

        $body = $response->json();
        Log::info('MercadoPagoPointService::cancelPaymentIntent - Response', [
            'status' => $response->status(),
            'body' => $body,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                $this->extractErrorMessage($body, $response->status(), 'Não foi possível cancelar o pagamento na maquininha.')
            );
        }
    }

    /**
     * Consulta o status de um Payment Intent (útil para polling alternativo).
     *
     * @return array{state?: string, id?: string, ...}
     */
    public function getPaymentIntentStatus(string $intentId): array
    {
        $token = $this->ensureTokenConfigured();

        $url = self::BASE_URL . "/point/integration-api/payment-intents/{$intentId}";
        $response = Http::withToken($token)->timeout(10)->get($url);

        return $response->json() ?? [];
    }

    private function extractErrorMessage(?array $body, int $status, string $fallback): string
    {
        if (! is_array($body)) {
            return $fallback;
        }
        $message = $body['message'] ?? $body['error'] ?? null;
        if (is_string($message)) {
            return $message;
        }
        if (isset($body['cause']) && is_array($body['cause']) && isset($body['cause'][0]['description'])) {
            return (string) $body['cause'][0]['description'];
        }

        return $fallback;
    }
}
