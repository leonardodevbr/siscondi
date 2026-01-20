<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'trade_name',
        'cnpj',
        'email',
        'phone',
        'active',
        'zip_code',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
    ];

    /**
     * @return HasMany<Product>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the full address as a formatted string.
     */
    public function getAddressFullAttribute(): string
    {
        $parts = [];

        if ($this->street) {
            $parts[] = $this->street;
        }

        if ($this->number) {
            $parts[] = 'nº ' . $this->number;
        }

        if ($this->complement) {
            $parts[] = $this->complement;
        }

        if ($this->neighborhood) {
            $parts[] = '- ' . $this->neighborhood;
        }

        if ($this->city || $this->state) {
            $cityState = array_filter([$this->city, $this->state]);
            if (!empty($cityState)) {
                $parts[] = implode('/', $cityState);
            }
        }

        if ($this->zip_code) {
            $parts[] = 'CEP: ' . $this->zip_code;
        }

        return implode(', ', $parts);
    }
}

