<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Manifestation extends Model
{
    protected $fillable = ['name', 'category', 'description'];

    public function culturalAgents(): BelongsToMany
    {
        return $this->belongsToMany(CulturalAgent::class, 'cultural_agent_manifestation');
    }
}
