<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementType: string
{
    case ENTRY = 'entry';
    case SALE = 'sale';
    case RETURN = 'return';
    case LOSS = 'loss';
    case ADJUSTMENT = 'adjustment';
}
