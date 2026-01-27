<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Enums\PaymentMethod;
use App\Models\Setting;
use App\Services\Payment\DTOs\PaymentData;
use App\Services\Payment\DTOs\PaymentResponse;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

/**
 * Implementação do Mercado Pago.
 */
class MercadoPagoGateway implements PaymentGatewayInterface
{
    public function __construct()
    {
        $token = Setting::get('mp_access_token');
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Token do Mercado Pago não configurado. Configure em Configurações > Integrações.');
        }

        MercadoPagoConfig::setAccessToken($token);
    }

    public function getName(): string
    {
        return 'mercadopago';
    }

    public function createPayment(PaymentData $data): PaymentResponse
    {
        if ($data->method !== PaymentMethod::PIX) {
            throw new \InvalidArgumentException('Mercado Pago (Online) suporta apenas PIX. Use Point para cartões.');
        }

        $request = [
            'transaction_amount' => round($data->amount, 2),
            'payment_method_id' => 'pix',
            'description' => $data->description ?? "Venda #{$data->saleId}",
            'payer' => [
                'email' => $data->payerEmail ?? 'pagamento@mercadopago.com',
            ],
            'external_reference' => (string) $data->saleId,
        ];

        $requestOptions = new RequestOptions();
        $requestOptions->setCustomHeaders([
            'X-Idempotency-Key: pix-sale-' . $data->saleId . '-' . time(),
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

            Log::error('MercadoPagoGateway::createPayment failed', [
                'sale_id' => $data->saleId,
                'error' => $userMsg,
                'response' => $decoded,
            ]);

            throw new \RuntimeException($userMsg ?: 'Erro ao gerar PIX no Mercado Pago.');
        }

        $poi = $payment->point_of_interaction ?? null;
        $txData = is_object($poi) && isset($poi->transaction_data) ? $poi->transaction_data : null;

        $qrCode = '';
        $qrCodeBase64 = '';
        if (is_object($txData)) {
            $qrCode = (string) ($txData->qr_code ?? '');
            $qrCodeBase64 = (string) ($txData->qr_code_base64 ?? '');
        }

        return new PaymentResponse(
            status: 'pending',
            qrCode: $qrCode,
            qrCodeBase64: $qrCodeBase64,
            transactionId: (string) ($payment->id ?? ''),
            rawResponse: (array) $payment,
        );
    }

    public function calculateInstallments(float $amount): array
    {
        // Mercado Pago Online não processa cartões. Retorna vazio.
        // Se quiser simular, leia as configurações da loja (como InstallmentService).
        $max = (int) (Setting::get('cc_max_installments') ?? 12);
        $noInterest = (int) (Setting::get('cc_no_interest_installments') ?? 3);
        $rate = (float) (Setting::get('cc_interest_rate') ?? 2.99) / 100;
        $minInstallment = (float) (Setting::get('cc_min_installment_value') ?? 10.0);

        $options = [];
        for ($t = 1; $t <= $max; $t++) {
            $interestFree = $t <= $noInterest;
            if ($interestFree) {
                $total = $amount;
                $installmentAmount = round($total / $t, 2);
            } else {
                $total = $amount * ((1 + $rate) ** $t);
                $total = round($total, 2);
                $installmentAmount = round($total / $t, 2);
            }

            if ($installmentAmount < $minInstallment) {
                continue;
            }

            $options[] = [
                'installment' => $t,
                'amount' => $installmentAmount,
                'total' => $total,
                'interest_free' => $interestFree,
            ];
        }

        return $options;
    }

    public function cancelPayment(string $transactionId): bool
    {
        // Mercado Pago permite cancelar pagamentos via API, mas não é comum para PIX
        // que expira automaticamente. Implementar se necessário.
        Log::warning('MercadoPagoGateway::cancelPayment não implementado', ['transaction_id' => $transactionId]);

        return false;
    }

    public function webhookHandler(Request $request): ?array
    {
        $payload = $request->all();
        $type = $payload['type'] ?? $payload['action'] ?? null;

        if ($type !== 'payment' && $type !== 'payment.updated') {
            return null;
        }

        $paymentId = $payload['data']['id'] ?? null;
        if (! $paymentId) {
            return null;
        }

        try {
            $client = new PaymentClient();
            $payment = $client->get((int) $paymentId);

            $status = (string) ($payment->status ?? '');
            $externalReference = (string) ($payment->external_reference ?? '');

            // Normaliza status
            $normalizedStatus = match ($status) {
                'approved' => 'approved',
                'rejected', 'cancelled' => 'rejected',
                default => 'pending',
            };

            return [
                'transaction_id' => (string) $payment->id,
                'status' => $normalizedStatus,
                'sale_id' => $externalReference ? (int) $externalReference : null,
            ];
        } catch (\Throwable $e) {
            Log::error('MercadoPagoGateway::webhookHandler failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
