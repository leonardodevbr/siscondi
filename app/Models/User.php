<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'operation_password',
        'operation_pin',
        'branch_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'operation_password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'operation_password' => 'hashed',
        ];
    }

    /**
     * Filial principal (legado - mantido por compatibilidade)
     * 
     * @return BelongsTo<Branch, User>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Todas as filiais que o usuário tem acesso
     * 
     * @return BelongsToMany<Branch>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Filial primária do usuário (via tabela pivot)
     * 
     * @return Branch|null
     */
    public function getPrimaryBranch(): ?Branch
    {
        return $this->branches()->wherePivot('is_primary', true)->first();
    }

    /**
     * IDs de todas as filiais que o usuário tem acesso
     * 
     * @return array<int>
     */
    public function getBranchIds(): array
    {
        // Super Admin e Owner têm acesso a todas as filiais
        if ($this->hasRole(['super-admin', 'owner'])) {
            return Branch::query()->pluck('id')->toArray();
        }

        return $this->branches()->pluck('branches.id')->toArray();
    }

    /**
     * Verifica se o usuário tem acesso a uma filial específica
     */
    public function hasAccessToBranch(int $branchId): bool
    {
        if ($this->hasRole(['super-admin', 'owner'])) {
            return true;
        }

        return $this->branches()->where('branches.id', $branchId)->exists();
    }

    /**
     * Verifica se é owner (dono da loja)
     */
    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }
}

