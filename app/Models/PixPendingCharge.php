<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PixPendingCharge extends Model
{
    protected $fillable = [
        'sale_id',
        'amount',
        'transaction_id',
        'gateway',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
