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
 * @property-read string $label_details
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

    /**
     * @return Attribute<string, never>
     */
    protected function labelDetails(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $attributesRaw = $this->getAttribute('attributes');
                
                if (empty($attributesRaw)) {
                    return '';
                }

                if (is_string($attributesRaw)) {
                    $attributes = json_decode($attributesRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($attributes)) {
                        return '';
                    }
                } elseif (is_array($attributesRaw)) {
                    $attributes = $attributesRaw;
                } else {
                    return '';
                }

                $values = [];
                $sizeKeys = ['tamanho', 'size', 'tam'];
                $colorKeys = ['cor', 'color', 'colour'];
                
                foreach ($sizeKeys as $sizeKey) {
                    foreach ($attributes as $key => $value) {
                        $keyNormalized = strtolower(trim((string) $key));
                        if ($keyNormalized === $sizeKey) {
                            $values[] = strtoupper(trim((string) $value));
                            break 2;
                        }
                    }
                }

                foreach ($colorKeys as $colorKey) {
                    foreach ($attributes as $key => $value) {
                        $keyNormalized = strtolower(trim((string) $key));
                        if ($keyNormalized === $colorKey) {
                            $values[] = strtoupper(trim((string) $value));
                            break 2;
                        }
                    }
                }

                return implode(' - ', $values);
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
