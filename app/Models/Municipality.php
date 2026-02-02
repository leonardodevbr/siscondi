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
        'display_name',
        'cnpj',
        'state',
        'display_state',
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

    /**
     * Cargos/posições do município
     *
     * @return HasMany<Position>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'municipality_id');
    }
}
