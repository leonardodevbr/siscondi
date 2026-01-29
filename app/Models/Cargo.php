<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cargo extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'municipality_id',
        'name',
        'symbol',
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
        return $this->belongsToMany(LegislationItem::class, 'cargo_legislation_item')
            ->withTimestamps();
    }

    /**
     * Servidores que ocupam este cargo
     *
     * @return BelongsToMany<Servant>
     */
    public function servants(): BelongsToMany
    {
        return $this->belongsToMany(Servant::class, 'servant_cargo')
            ->withTimestamps();
    }
}
