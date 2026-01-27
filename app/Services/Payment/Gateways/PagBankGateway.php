<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Enums\PaymentMethod;
use App\Models\Setting;
use App\Services\Payment\DTOs\PaymentData;
use App\Services\Payment\DTOs\PaymentResponse;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Implementação do PagBank (PagSeguro).
 */
class PagBankGateway implements PaymentGatewayInterface
{
    private string $token;

    private string $baseUrl;

    public function __construct()
    {
        $this->token = (string) Setting::get('pagbank_token', '');
        if ($this->token === '') {
            throw new \RuntimeException('Token do PagBank não configurado. Configure em Configurações > Integrações.');
        }

        $isSandbox = (bool) Setting::get('pagbank_sandbox', false);
        $this->baseUrl = $isSandbox
            ? 'https://sandbox.api.pagseguro.com'
            : 'https://api.pagseguro.com';
    }

    public function getName(): string
    {
        return 'pagbank';
    }

    public function createPayment(PaymentData $data): PaymentResponse
    {
        if ($data->method !== PaymentMethod::PIX) {
            throw new \InvalidArgumentException('PagBank atualmente suporta apenas PIX nesta implementação.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/orders', [
            'reference_id' => (string) $data->saleId,
            'customer' => [
                'name' => 'Cliente PDV',
                'email' => $data->payerEmail ?? 'cliente@pdv.com.br',
                'tax_id' => '00000000000',
            ],
            'items' => [
                [
                    'reference_id' => 'item-' . $data->saleId,
                    'name' => $data->description ?? "Venda #{$data->saleId}",
                    'quantity' => 1,
                    'unit_amount' => (int) round($data->amount * 100),
                ],
            ],
            'qr_codes' => [
                [
                    'amount' => [
                        'value' => (int) round($data->amount * 100),
                    ],
                ],
            ],
            'notification_urls' => [
                url('/api/webhooks/pagbank'),
            ],
        ]);

        if (! $response->successful()) {
            $error = $response->json('error_messages.0.description') ?? $response->body();
            Log::error('PagBankGateway::createPayment failed', [
                'sale_id' => $data->saleId,
                'status' => $response->status(),
                'error' => $error,
            ]);

            throw new \RuntimeException('Erro ao gerar PIX no PagBank: ' . $error);
        }

        $responseData = $response->json();
        $qrCode = $responseData['qr_codes'][0]['text'] ?? '';
        $qrCodeBase64 = $responseData['qr_codes'][0]['links'][0]['href'] ?? '';

        return new PaymentResponse(
            status: 'pending',
            qrCode: $qrCode,
            qrCodeBase64: $qrCodeBase64,
            transactionId: (string) ($responseData['id'] ?? ''),
            rawResponse: $responseData,
        );
    }

    public function calculateInstallments(float $amount): array
    {
        // PagBank tem sua própria API de simulação. Por enquanto, usa as configs da loja.
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
        Log::warning('PagBankGateway::cancelPayment não implementado', ['transaction_id' => $transactionId]);

        return false;
    }

    public function webhookHandler(Request $request): ?array
    {
        // PagBank envia notificações em formato específico
        $payload = $request->all();
        $notificationCode = $payload['notificationCode'] ?? null;

        if (! $notificationCode) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])->get($this->baseUrl . '/notifications/' . $notificationCode);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $status = $data['status'] ?? '';
            $referenceId = $data['reference_id'] ?? '';

            $normalizedStatus = match ($status) {
                'PAID' => 'approved',
                'CANCELED', 'DECLINED' => 'rejected',
                default => 'pending',
            };

            return [
                'transaction_id' => (string) ($data['id'] ?? ''),
                'status' => $normalizedStatus,
                'sale_id' => $referenceId ? (int) $referenceId : null,
            ];
        } catch (\Throwable $e) {
            Log::error('PagBankGateway::webhookHandler failed', [
                'notification_code' => $notificationCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
