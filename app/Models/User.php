<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasPushSubscriptions, Notifiable, HasRoles;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'municipality_id',
        'primary_department_id',
        'cargo_id',
        'signature_path',
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
     * Município ao qual o usuário pertence (cadastro).
     *
     * @return BelongsTo<Municipality, $this>
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Secretaria em que o usuário está "atuando" no momento (escolhida no login quando tem mais de uma).
     *
     * @return BelongsTo<Department, $this>
     */
    public function primaryDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'primary_department_id');
    }

    /**
     * Cargo do usuário (define o perfil/role no sistema).
     *
     * @return BelongsTo<Cargo, $this>
     */
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    /**
     * Secretarias/setores que o usuário tem acesso (todas do município do usuário).
     *
     * @return BelongsToMany<Department>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_user')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Secretaria principal (em que está atuando): coluna primary_department_id ou fallback no pivot is_primary.
     */
    public function getPrimaryDepartment(): ?Department
    {
        if ($this->primary_department_id !== null) {
            $dept = $this->primaryDepartment;
            if ($dept && $this->departments()->where('departments.id', $dept->id)->exists()) {
                return $dept;
            }
        }

        return $this->departments()->wherePivot('is_primary', true)->first();
    }

    /**
     * Município do usuário (registro na tabela users).
     */
    public function getMunicipality(): ?Municipality
    {
        return $this->municipality;
    }

    /**
     * @return array<int>
     */
    public function getDepartmentIds(): array
    {
        if ($this->hasRole('super-admin')) {
            return Department::query()->pluck('id')->toArray();
        }
        if ($this->hasRole('admin') && $this->municipality_id !== null) {
            return Department::query()->where('municipality_id', $this->municipality_id)->pluck('id')->toArray();
        }

        return $this->departments()->pluck('departments.id')->toArray();
    }

    public function hasAccessToDepartment(int $departmentId): bool
    {
        if ($this->hasRole('super-admin')) {
            return true;
        }
        if ($this->hasRole('admin') && $this->municipality_id !== null) {
            return Department::query()->where('id', $departmentId)->where('municipality_id', $this->municipality_id)->exists();
        }

        return $this->departments()->where('departments.id', $departmentId)->exists();
    }

    /**
     * Verifica se o usuário precisa escolher secretaria no login (>1 secretaria e não é admin).
     */
    public function needsPrimaryDepartmentChoice(): bool
    {
        if ($this->hasRole('super-admin') || $this->hasRole('admin')) {
            return false;
        }
        $count = $this->departments()->count();

        return $count > 1;
    }

    /**
     * @return HasOne<Servant>
     */
    public function servant(): HasOne
    {
        return $this->hasOne(Servant::class);
    }

    /**
     * Verifica se o usuário tem PIN de operação definido (para confirmação ao assinar).
     */
    public function hasOperationPin(): bool
    {
        $pin = $this->getRawOriginal('operation_pin') ?? $this->attributes['operation_pin'] ?? null;

        return $pin !== null && trim((string) $pin) !== '';
    }

    /**
     * Verifica se o usuário tem senha de operação definida (para confirmação ao assinar).
     */
    public function hasOperationPassword(): bool
    {
        $stored = $this->getRawOriginal('operation_password') ?? $this->attributes['operation_password'] ?? null;

        return $stored !== null && (string) $stored !== '';
    }

    /**
     * Verifica se o usuário exige confirmação com senha e/ou PIN ao assinar.
     */
    public function requiresOperationCredentialsToSign(): bool
    {
        return $this->hasOperationPin() || $this->hasOperationPassword();
    }
}
