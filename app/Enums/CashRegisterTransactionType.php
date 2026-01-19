<?php

declare(strict_types=1);

namespace App\Enums;

enum CashRegisterTransactionType: string
{
    case OPENING_BALANCE = 'opening_balance';
    case SALE = 'sale';
    case SUPPLY = 'supply';
    case BLEED = 'bleed';
    case CLOSING_BALANCE = 'closing_balance';
}
