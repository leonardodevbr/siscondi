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
        'code',
        'title',
        'law_number',
        'daily_value',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'daily_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Servidores vinculados a esta legislação
     * 
     * @return HasMany<Servant>
     */
    public function servants(): HasMany
    {
        return $this->hasMany(Servant::class);
    }

    /**
     * Solicitações de diárias que usam esta legislação como snapshot
     * 
     * @return HasMany<DailyRequest>
     */
    public function dailyRequests(): HasMany
    {
        return $this->hasMany(DailyRequest::class, 'legislation_snapshot_id');
    }
}
