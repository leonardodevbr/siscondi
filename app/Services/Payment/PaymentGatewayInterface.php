<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Sale;

interface PaymentGatewayInterface
{
    /**
     * Generate PIX payment for a sale.
     *
     * @return array{emv_payload: string, qrcode_base64: string, transaction_id: string}
     */
    public function generatePix(Sale $sale): array;

    /**
     * Process webhook from payment gateway.
     *
     * @param array<string, mixed> $payload
     * @return string|null Transaction ID if processed, null otherwise
     */
    public function processWebhook(array $payload): ?string;
}
