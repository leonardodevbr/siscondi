<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'supplier_id',
        'name',
        'description',
        'sku',
        'barcode',
        'cost_price',
        'sell_price',
        'promotional_price',
        'promotional_expires_at',
        'stock_quantity',
        'min_stock_quantity',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'cost_price' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'promotional_price' => 'decimal:2',
        'promotional_expires_at' => 'datetime',
        'stock_quantity' => 'integer',
        'min_stock_quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<Category, Product>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Supplier, Product>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<StockMovement>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getEffectivePrice(): float
    {
        if ($this->promotional_price !== null && $this->promotional_expires_at !== null) {
            if (now()->isBefore($this->promotional_expires_at)) {
                return (float) $this->promotional_price;
            }
        }

        return (float) $this->sell_price;
    }

    public function hasActivePromotion(): bool
    {
        return $this->promotional_price !== null
            && $this->promotional_expires_at !== null
            && now()->isBefore($this->promotional_expires_at);
    }
}

