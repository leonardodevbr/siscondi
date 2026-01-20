<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'cpf_cnpj',
        'email',
        'phone',
        'birth_date',
        'zip_code',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'birth_date' => 'date',
    ];

    /**
     * @return HasMany<Sale>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
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

