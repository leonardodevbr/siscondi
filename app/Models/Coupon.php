<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'type',
        'value',
        'max_discount_amount',
        'min_purchase_amount',
        'starts_at',
        'expires_at',
        'usage_limit',
        'used_count',
        'active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => CouponType::class,
        'value' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($coupon): void {
            $coupon->code = strtoupper($coupon->code);
        });

        static::updating(function ($coupon): void {
            if ($coupon->isDirty('code')) {
                $coupon->code = strtoupper($coupon->code);
            }
        });
    }

    public function isValid(float $purchaseAmount = 0): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->starts_at && now()->isBefore($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && now()->isAfter($this->expires_at)) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        if ($this->min_purchase_amount !== null && $purchaseAmount < (float) $this->min_purchase_amount) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $totalAmount): float
    {
        if ($this->type === CouponType::PERCENTAGE) {
            $discount = $totalAmount * ((float) $this->value / 100);
            if ($this->max_discount_amount !== null) {
                $discount = min($discount, (float) $this->max_discount_amount);
            }
            return min($discount, $totalAmount);
        }

        return min((float) $this->value, $totalAmount);
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
