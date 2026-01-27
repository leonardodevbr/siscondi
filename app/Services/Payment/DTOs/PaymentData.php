<?php

declare(strict_types=1);

namespace App\Services\Payment\DTOs;

use App\Enums\PaymentMethod;

/**
 * DTO para dados de entrada do pagamento.
 */
readonly class PaymentData
{
    public function __construct(
        public int $saleId,
        public float $amount,
        public PaymentMethod $method,
        public int $installments = 1,
        public ?string $description = null,
        public ?string $payerEmail = null,
    ) {
    }
}
