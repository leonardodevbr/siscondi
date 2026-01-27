<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Setting;
use App\Services\Payment\Gateways\MercadoPagoGateway;
use App\Services\Payment\Gateways\PagBankGateway;
use Illuminate\Support\Facades\Log;

/**
 * Factory que retorna a instância correta do Gateway de Pagamento
 * baseado nas configurações da filial ou global.
 */
class PaymentGatewayFactory
{
    /**
     * Retorna o gateway ativo para a filial especificada.
     * Se não houver configuração específica da filial, usa a configuração global.
     *
     * @param int|null $branchId ID da filial (opcional)
     * @return PaymentGatewayInterface Instância do gateway
     * @throws \RuntimeException Se nenhum gateway estiver configurado
     */
    public function getGateway(?int $branchId = null): PaymentGatewayInterface
    {
        // Por enquanto, usa configuração global. No futuro, pode buscar por branch_id.
        // Exemplo: $gateway = Setting::get("branch.{$branchId}.payment_gateway");
        $gateway = (string) Setting::get('payment_gateway', '');

        if ($gateway === '') {
            throw new \RuntimeException('Nenhum gateway de pagamento configurado. Configure em Configurações > Integrações.');
        }

        return match ($gateway) {
            'mercadopago' => new MercadoPagoGateway(),
            'pagbank' => new PagBankGateway(),
            default => throw new \RuntimeException("Gateway '{$gateway}' não suportado."),
        };
    }

    /**
     * Retorna o gateway especificado explicitamente.
     *
     * @param string $gatewayName Nome do gateway ('mercadopago' ou 'pagbank')
     * @return PaymentGatewayInterface Instância do gateway
     * @throws \RuntimeException Se o gateway não for suportado
     */
    public function getGatewayByName(string $gatewayName): PaymentGatewayInterface
    {
        return match ($gatewayName) {
            'mercadopago' => new MercadoPagoGateway(),
            'pagbank' => new PagBankGateway(),
            default => throw new \RuntimeException("Gateway '{$gatewayName}' não suportado."),
        };
    }

    /**
     * Retorna lista de gateways disponíveis.
     *
     * @return array<string, string> Array com chave = identificador, valor = nome amigável
     */
    public function getAvailableGateways(): array
    {
        return [
            'mercadopago' => 'Mercado Pago',
            'pagbank' => 'PagBank',
        ];
    }
}
