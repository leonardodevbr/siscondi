<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Services\Payment\DTOs\PaymentData;
use App\Services\Payment\DTOs\PaymentResponse;
use Illuminate\Http\Request;

/**
 * Interface unificada para todos os gateways de pagamento.
 * Cada gateway (Mercado Pago, PagBank) deve implementar esta interface.
 */
interface PaymentGatewayInterface
{
    /**
     * Cria um pagamento (PIX, QR Code ou Link de pagamento).
     *
     * @param PaymentData $data Dados do pagamento
     * @return PaymentResponse Resposta com QR Code, link ou dados do pagamento
     */
    public function createPayment(PaymentData $data): PaymentResponse;

    /**
     * Calcula opções de parcelamento com base nas taxas configuradas.
     *
     * @param float $amount Valor total
     * @return array<int, array{installment: int, amount: float, total: float, interest_free: bool}> Array de opções de parcelamento
     */
    public function calculateInstallments(float $amount): array;

    /**
     * Cancela um pagamento existente.
     *
     * @param string $transactionId ID da transação no gateway
     * @return bool True se cancelado com sucesso
     */
    public function cancelPayment(string $transactionId): bool;

    /**
     * Processa webhook do gateway e normaliza o status.
     *
     * @param Request $request Request completo do webhook
     * @return array{transaction_id: string, status: string, sale_id: ?int}|null Dados normalizados ou null se inválido
     */
    public function webhookHandler(Request $request): ?array;

    /**
     * Retorna o nome do gateway.
     *
     * @return string Nome do gateway (ex: 'mercadopago', 'pagbank')
     */
    public function getName(): string;
}
