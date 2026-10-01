<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    protected $fillable = ['name', 'state', 'slug'];

    public function communities(): HasMany { return $this->hasMany(Community::class); }
    public function culturalAgents(): HasMany { return $this->hasMany(CulturalAgent::class); }
}
