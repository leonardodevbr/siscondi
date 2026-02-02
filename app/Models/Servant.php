<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servant extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'department_id',
        'position_id',
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
        'appointment_decree',
        'appointment_date',
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
     * Cargo/posição do servidor
     *
     * @return BelongsTo<Position>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Item da legislação efetivo para cálculo de diária.
     * 
     * Retorna o primeiro item de legislação associado ao cargo (position) do servidor
     * que tenha valores definidos.
     */
    public function getEffectiveLegislationItem(): ?LegislationItem
    {
        $this->loadMissing('position.legislationItems');
        
        if ($this->position && $this->position->legislationItems->isNotEmpty()) {
            foreach ($this->position->legislationItems as $item) {
                if (! empty($item->values)) {
                    return $item;
                }
            }
        }

        return null;
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
