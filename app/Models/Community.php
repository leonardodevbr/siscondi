<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Community extends Model
{
    protected $fillable = ['municipality_id', 'name', 'zone', 'latitude', 'longitude'];

    protected $casts = ['latitude' => 'float', 'longitude' => 'float'];

    public function municipality(): BelongsTo { return $this->belongsTo(Municipality::class); }
    public function culturalAgents(): HasMany { return $this->hasMany(CulturalAgent::class); }
}
