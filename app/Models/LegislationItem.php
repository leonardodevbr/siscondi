<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegislationItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'legislation_id',
        'functional_category',
        'daily_class',
        'values',
    ];

    /**
     * Valores por destino: chave = label do destino, valor = centavos (int).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'values' => 'array',
    ];

    /**
     * Legislação a que pertence o item
     *
     * @return BelongsTo<Legislation, LegislationItem>
     */
    public function legislation(): BelongsTo
    {
        return $this->belongsTo(Legislation::class);
    }

    /**
     * Servidores vinculados a este item (cargo/classe)
     *
     * @return HasMany<Servant>
     */
    public function servants(): HasMany
    {
        return $this->hasMany(Servant::class, 'legislation_item_id');
    }

    /**
     * Cargos/posições vinculados a este item da legislação
     *
     * @return BelongsToMany<Position>
     */
    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(Position::class, 'legislation_item_position')
            ->withTimestamps();
    }

    /**
     * Retorna o valor da diária para um destino (label definido na legislação), em centavos.
     */
    public function getValueForDestination(string $destinationLabel): int
    {
        $values = $this->values ?? [];

        return (int) ($values[$destinationLabel] ?? 0);
    }
}
