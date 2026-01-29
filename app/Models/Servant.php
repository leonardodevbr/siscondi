<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servant extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'legislation_id',
        'department_id',
        'name',
        'cpf',
        'rg',
        'organ_expeditor',
        'matricula',
        'bank_name',
        'agency_number',
        'account_number',
        'account_type',
        'email',
        'phone',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Usuário associado ao servidor (opcional)
     * 
     * @return BelongsTo<User, Servant>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cargo/Legislação do servidor
     * 
     * @return BelongsTo<Legislation, Servant>
     */
    public function legislation(): BelongsTo
    {
        return $this->belongsTo(Legislation::class);
    }

    /**
     * Secretaria/setor de lotação
     *
     * @return BelongsTo<Department, Servant>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Solicitações de diárias do servidor
     * 
     * @return HasMany<DailyRequest>
     */
    public function dailyRequests(): HasMany
    {
        return $this->hasMany(DailyRequest::class);
    }

    /**
     * Formata o CPF para exibição
     */
    public function getFormattedCpfAttribute(): string
    {
        $cpf = $this->cpf;
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
}
