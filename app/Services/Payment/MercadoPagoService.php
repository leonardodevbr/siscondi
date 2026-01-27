<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Sale;
use App\Models\Setting;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

/**
 * Serviço PIX Real via API Online do Mercado Pago (PaymentClient).
 * Usa Setting::get('mp_access_token') para autenticação.
 */
class MercadoPagoService
{
    /**
     * Cria pagamento PIX para a venda e retorna dados do QR Code.
     *
     * @return array{qr_code: string, qr_code_base64: string, payment_id: int|string}
     */
    public function createPixPayment(Sale $sale): array
    {
        $token = Setting::get('mp_access_token');
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Token do Mercado Pago não configurado. Conecte em Configurações > Integrações.');
        }

        MercadoPagoConfig::setAccessToken($token);

        $amount = (float) $sale->final_amount;

        // Para PIX, o payer.email é obrigatório mas pode ser qualquer e-mail válido.
        // O pagamento é creditado na conta autenticada (mp_access_token), não no e-mail do payer.
        $request = [
            'transaction_amount' => round($amount, 2),
            'payment_method_id' => 'pix',
            'description' => 'Venda #' . $sale->id,
            'payer' => [
                'email' => 'leo.nun.o@gmail.com',
            ],
            'external_reference' => (string) $sale->id,
        ];

        $requestOptions = new RequestOptions();
        $requestOptions->setCustomHeaders([
            'X-Idempotency-Key: pix-sale-' . $sale->id . '-' . time(),
        ]);

        $client = new PaymentClient();

        try {
            $payment = $client->create($request, $requestOptions);
        } catch (MPApiException $e) {
            $response = $e->getApiResponse();
            $content = $response->getContent();
            $decoded = is_string($content) ? json_decode($content, true) : (is_array($content) ? $content : null);
            $message = $decoded['message'] ?? null;
            $cause = $decoded['cause'] ?? null;
            $firstCause = is_array($cause) && isset($cause[0]) ? $cause[0] : null;
            $detail = is_array($firstCause) ? ($firstCause['description'] ?? $firstCause['message'] ?? null) : null;
            $userMsg = $detail ?? $message ?? $e->getMessage();
            if (is_string($userMsg) && $userMsg !== '') {
                throw new \RuntimeException($userMsg);
            }
            throw new \RuntimeException('Erro ao gerar PIX no Mercado Pago. Verifique o token e as credenciais em Configurações > Integrações.');
        }

        $poi = $payment->point_of_interaction ?? null;
        $txData = is_object($poi) && isset($poi->transaction_data) ? $poi->transaction_data : null;

        $qrCode = '';
        $qrCodeBase64 = '';
        if (is_object($txData)) {
            $qrCode = (string) ($txData->qr_code ?? '');
            $qrCodeBase64 = (string) ($txData->qr_code_base64 ?? '');
        }

        return [
            'qr_code' => $qrCode,
            'qr_code_base64' => $qrCodeBase64,
            'payment_id' => $payment->id ?? 0,
        ];
    }

    /**
     * Busca um pagamento no MP por ID (para webhook).
     *
     * @return array{id: int, status: string, external_reference: string}|null
     */
    public function getPayment(int $id): ?array
    {
        $token = Setting::get('mp_access_token');
        if (! is_string($token) || $token === '') {
            return null;
        }

        MercadoPagoConfig::setAccessToken($token);
        $client = new PaymentClient();
        $payment = $client->get($id);

        return [
            'id' => $payment->id,
            'status' => (string) ($payment->status ?? ''),
            'external_reference' => (string) ($payment->external_reference ?? ''),
        ];
    }

    private function resolvePayerEmail(Sale $sale): string
    {
        // Para PIX, o payer.email é apenas um campo obrigatório da API do MP.
        // O pagamento é creditado na conta autenticada (mp_access_token), não no e-mail do payer.
        // Usamos e-mail genérico válido para qualquer venda.
        return 'pagamento@mercadopago.com';
    }
}
