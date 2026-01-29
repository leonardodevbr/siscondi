<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'is_main',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_main' => 'boolean',
    ];

    /**
     * Usuários que têm acesso a esta secretaria
     *
     * @return BelongsToMany<User>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_user')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Servidores lotados nesta secretaria
     *
     * @return HasMany<Servant>
     */
    public function servants(): HasMany
    {
        return $this->hasMany(Servant::class, 'department_id');
    }
}
