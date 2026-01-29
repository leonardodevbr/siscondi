<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Legislation extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'law_number',
        'is_active',
        'destinations',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'destinations' => 'array',
    ];

    /**
     * Itens da tabela de valores (categoria, classe, valores por destino)
     *
     * @return HasMany<LegislationItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LegislationItem::class, 'legislation_id');
    }

    /**
     * Servidores vinculados via itens desta legislação
     *
     * @return HasMany<Servant>
     */
    public function servants(): HasMany
    {
        return $this->hasManyThrough(Servant::class, LegislationItem::class, 'legislation_id', 'legislation_item_id');
    }
}
