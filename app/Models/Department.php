<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'municipality_id',
        'parent_id',
        'name',
        'is_main',
        'fund_cnpj',
        'fund_name',
        'fund_code',
        'logo_path',
        'code',
        'description',
        'total_employees'
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_main' => 'boolean',
    ];

    /**
     * Município ao qual a secretaria pertence
     *
     * @return BelongsTo<Municipality, $this>
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

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

    /**
     * Departamento pai (hierarquia)
     *
     * @return BelongsTo<Department, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    /**
     * Subdepartamentos (filhos na hierarquia)
     *
     * @return HasMany<Department>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }
}
