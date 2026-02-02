<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'municipality_id',
        'name',
        'symbol',
        'salary',
        'description',
        'total_positions',
        'role',
    ];

    /**
     * Município ao qual o cargo pertence
     *
     * @return BelongsTo<Municipality, $this>
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Itens de legislação vinculados a este cargo (valores/classe de diária)
     *
     * @return BelongsToMany<LegislationItem>
     */
    public function legislationItems(): BelongsToMany
    {
        return $this->belongsToMany(LegislationItem::class, 'legislation_item_position')
            ->withTimestamps();
    }

    /**
     * Servidores que ocupam este cargo
     *
     * @return HasMany<Servant>
     */
    public function servants(): HasMany
    {
        return $this->hasMany(Servant::class);
    }
}
