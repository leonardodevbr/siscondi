<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class InvalidCouponException extends Exception
{
    public static function notFound(string $code): self
    {
        return new self("Coupon code '{$code}' not found.");
    }

    public static function expired(string $code): self
    {
        return new self("Coupon code '{$code}' has expired.");
    }

    public static function notYetActive(string $code): self
    {
        return new self("Coupon code '{$code}' is not yet active.");
    }

    public static function usageLimitReached(string $code): self
    {
        return new self("Coupon code '{$code}' has reached its usage limit.");
    }

    public static function minimumPurchaseNotMet(string $code, float $minAmount): self
    {
        return new self("Coupon code '{$code}' requires a minimum purchase of " . number_format($minAmount, 2, ',', '.') . ".");
    }

    public static function inactive(string $code): self
    {
        return new self("Coupon code '{$code}' is inactive.");
    }
}
