<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sale_id',
        'method',
        'amount',
        'installments',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'installments' => 'integer',
        'method' => PaymentMethod::class,
    ];

    /**
     * @return BelongsTo<Sale, SalePayment>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
