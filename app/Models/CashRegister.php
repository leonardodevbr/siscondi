<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashRegisterStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'opened_at',
        'closed_at',
        'initial_balance',
        'final_balance',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'initial_balance' => 'decimal:2',
        'final_balance' => 'decimal:2',
        'status' => CashRegisterStatus::class,
    ];

    /**
     * @return BelongsTo<User, CashRegister>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CashRegisterTransaction>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(CashRegisterTransaction::class);
    }

    public function getCurrentBalance(): float
    {
        $transactionsSum = $this->transactions()
            ->where('type', '!=', \App\Enums\CashRegisterTransactionType::CLOSING_BALANCE->value)
            ->sum('amount');

        return (float) $this->initial_balance + (float) $transactionsSum;
    }
}
