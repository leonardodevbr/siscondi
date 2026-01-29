<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'value_up_to_200km',
        'value_above_200km',
        'value_state_capital',
        'value_other_capitals_df',
        'value_exterior',
    ];

    /**
     * Valores em centavos (integer).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value_up_to_200km' => 'integer',
        'value_above_200km' => 'integer',
        'value_state_capital' => 'integer',
        'value_other_capitals_df' => 'integer',
        'value_exterior' => 'integer',
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
     * Retorna o valor da diária para um tipo de destino (em centavos).
     */
    public function getValueForDestination(string $destinationType): int
    {
        return match ($destinationType) {
            'up_to_200km' => (int) $this->value_up_to_200km,
            'above_200km' => (int) $this->value_above_200km,
            'state_capital' => (int) $this->value_state_capital,
            'other_capitals_df' => (int) $this->value_other_capitals_df,
            'exterior' => (int) $this->value_exterior,
            default => 0,
        };
    }
}
