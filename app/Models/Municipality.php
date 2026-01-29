<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'cnpj',
        'state',
        'address',
        'email',
        'logo_path',
    ];

    /**
     * Secretarias do município
     *
     * @return HasMany<Department>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'municipality_id');
    }
}
