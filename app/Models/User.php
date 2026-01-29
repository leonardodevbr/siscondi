<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Secretarias que o usuário tem acesso
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
     * Secretaria primária do usuário
     */
    public function getPrimaryBranch(): ?Branch
    {
        return $this->branches()->wherePivot('is_primary', true)->first();
    }

    /**
     * IDs de todas as secretarias que o usuário tem acesso
     * 
     * @return array<int>
     */
    public function getBranchIds(): array
    {
        if ($this->hasRole('admin')) {
            return Branch::query()->pluck('id')->toArray();
        }

        return $this->branches()->pluck('branches.id')->toArray();
    }

    /**
     * Verifica se o usuário tem acesso a uma secretaria específica
     */
    public function hasAccessToBranch(int $branchId): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        return $this->branches()->where('branches.id', $branchId)->exists();
    }

    /**
     * Servidor associado ao usuário (se for um servidor público)
     * 
     * @return HasOne<Servant>
     */
    public function servant(): HasOne
    {
        return $this->hasOne(Servant::class);
    }
}

