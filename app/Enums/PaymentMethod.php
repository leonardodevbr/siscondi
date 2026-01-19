<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case MONEY = 'money';
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case PIX = 'pix';
    case STORE_CREDIT = 'store_credit';
}
