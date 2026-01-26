<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case MONEY = 'money';       // Dinheiro (espécie). No PDV exibido como "Dinheiro".
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case PIX = 'pix';
    case STORE_CREDIT = 'store_credit';

    /**
     * Mercado Pago Point (maquininha física). No PDV o operador vê "Cartão de Crédito" ou
     * "Cartão de Débito"; o sistema grava point quando activePaymentGateway === 'mercadopago_point'.
     */
    case POINT = 'point';
}
