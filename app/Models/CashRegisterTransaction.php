<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashRegisterTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRegisterTransaction extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cash_register_id',
        'type',
        'amount',
        'description',
        'sale_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'type' => CashRegisterTransactionType::class,
    ];

    /**
     * @return BelongsTo<CashRegister, CashRegisterTransaction>
     */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /**
     * @return BelongsTo<Sale, CashRegisterTransaction>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
