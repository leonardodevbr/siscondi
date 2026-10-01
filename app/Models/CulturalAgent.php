<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CulturalAgent extends Model
{
    protected $fillable = [
        'municipality_id', 'community_id', 'name', 'artistic_name', 'birth_date',
        'cpf', 'rg', 'phone', 'email', 'address', 'natural_city', 'natural_state',
        'education', 'occupation', 'other_occupation', 'years_in_community',
        'latitude', 'longitude', 'traditional_knowledge', 'teaches_knowledge',
        'biography', 'public_profile_enabled', 'status'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'latitude' => 'float',
        'longitude' => 'float',
        'teaches_knowledge' => 'boolean',
        'public_profile_enabled' => 'boolean',
    ];

    protected $hidden = ['cpf', 'rg', 'address'];

    public function municipality(): BelongsTo { return $this->belongsTo(Municipality::class); }
    public function community(): BelongsTo { return $this->belongsTo(Community::class); }
    public function manifestations(): BelongsToMany
    {
        return $this->belongsToMany(Manifestation::class, 'cultural_agent_manifestation')->withTimestamps();
    }
}
