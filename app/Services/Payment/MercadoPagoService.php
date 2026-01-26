<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Sale;
use App\Models\Setting;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
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
        $payerEmail = $this->resolvePayerEmail($sale);

        $request = [
            'transaction_amount' => round($amount, 2),
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $payerEmail,
            ],
            'external_reference' => (string) $sale->id,
        ];

        $requestOptions = new RequestOptions();
        $requestOptions->setCustomHeaders([
            'X-Idempotency-Key: pix-sale-' . $sale->id . '-' . time(),
        ]);

        $client = new PaymentClient();
        $payment = $client->create($request, $requestOptions);

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
        $email = $sale->customer?->email ?? null;
        if (is_string($email) && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        $store = (string) (Setting::get('store_email') ?? 'venda@loja.local');

        return $store !== '' ? $store : 'venda@loja.local';
    }
}
