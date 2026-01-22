<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'variation_id',
        'user_id',
        'branch_id',
        'type',
        'operation',
        'quantity',
        'reason',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<Branch, InventoryMovement>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Product, InventoryMovement>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, InventoryMovement>
     */
    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variation_id');
    }

    /**
     * @return BelongsTo<User, InventoryMovement>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
