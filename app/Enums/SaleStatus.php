<?php

declare(strict_types=1);

namespace App\Enums;

enum SaleStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';
}
