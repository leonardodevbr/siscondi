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
     * Secretarias/setores que o usuário tem acesso
     *
     * @return BelongsToMany<Department>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_user')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function getPrimaryDepartment(): ?Department
    {
        return $this->departments()->wherePivot('is_primary', true)->first();
    }

    /**
     * @return array<int>
     */
    public function getDepartmentIds(): array
    {
        if ($this->hasRole('admin')) {
            return Department::query()->pluck('id')->toArray();
        }

        return $this->departments()->pluck('departments.id')->toArray();
    }

    public function hasAccessToDepartment(int $departmentId): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        return $this->departments()->where('departments.id', $departmentId)->exists();
    }

    /**
     * @return HasOne<Servant>
     */
    public function servant(): HasOne
    {
        return $this->hasOne(Servant::class);
    }
}
