<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $description_full
 */

class ProductVariant extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'price',
        'image',
        'attributes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'attributes' => 'array',
    ];

    /**
     * @return BelongsTo<Product, ProductVariant>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<Inventory>
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * @return HasMany<SaleItem>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<StockMovement>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function descriptionFull(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $productName = $this->product->name;
                $attributes = $this->attributes ?? [];

                if (empty($attributes)) {
                    return $productName;
                }

                $attributeParts = [];
                foreach ($attributes as $key => $value) {
                    $attributeParts[] = ucfirst($key) . ': ' . $value;
                }

                return $productName . ' - ' . implode(' / ', $attributeParts);
            }
        );
    }

    public function getEffectivePrice(): float
    {
        if ($this->price !== null) {
            return (float) $this->price;
        }

        return $this->product->getEffectivePrice();
    }
}
