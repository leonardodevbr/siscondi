<?php

declare(strict_types=1);

namespace App\Services\Payment\DTOs;

/**
 * DTO para resposta do pagamento.
 * Formato agnóstico para qualquer gateway.
 */
readonly class PaymentResponse
{
    /**
     * @param string $status Status normalizado: 'pending', 'approved', 'rejected'
     * @param string|null $qrCode QR Code string (PIX Copia e Cola)
     * @param string|null $qrCodeBase64 QR Code em Base64 (imagem)
     * @param string|null $paymentUrl URL de pagamento (para link de checkout)
     * @param string|null $transactionId ID da transação no gateway
     * @param array<string, mixed> $rawResponse Resposta completa do gateway (para debug)
     */
    public function __construct(
        public string $status,
        public ?string $qrCode = null,
        public ?string $qrCodeBase64 = null,
        public ?string $paymentUrl = null,
        public ?string $transactionId = null,
        public array $rawResponse = [],
    ) {
    }

    /**
     * Converte para array para resposta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'qr_code' => $this->qrCode,
            'qr_code_base64' => $this->qrCodeBase64,
            'payment_url' => $this->paymentUrl,
            'transaction_id' => $this->transactionId,
        ];
    }
}
