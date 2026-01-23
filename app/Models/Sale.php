<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'branch_id',
        'cash_register_id',
        'customer_id',
        'coupon_id',
        'coupon_code',
        'total_amount',
        'discount_amount',
        'final_amount',
        'status',
        'note',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'status' => SaleStatus::class,
    ];

    /**
     * @return BelongsTo<User, Sale>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Branch, Sale>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Customer, Sale>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<CashRegister, Sale>
     */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /**
     * @return BelongsTo<Coupon, Sale>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return HasMany<SaleItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<Payment>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<SalePayment>
     */
    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /**
     * @return HasMany<CashRegisterTransaction>
     */
    public function cashRegisterTransactions(): HasMany
    {
        return $this->hasMany(CashRegisterTransaction::class);
    }
}
