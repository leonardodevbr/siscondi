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
        'legislation_item_id',
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
     * Item da legislação (categoria/classe/valores de diária)
     *
     * @return BelongsTo<LegislationItem, Servant>
     */
    public function legislationItem(): BelongsTo
    {
        return $this->belongsTo(LegislationItem::class, 'legislation_item_id');
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
     * Prioridade:
     * 1. Item de legislação associado ao cargo (position) do servidor via pivot
     * 2. Item de legislação vinculado diretamente ao servidor (legacy/fallback)
     */
    public function getEffectiveLegislationItem(): ?LegislationItem
    {
        // Primeiro: buscar via cargo (position) - a forma correta
        $this->loadMissing('position.legislationItems');
        
        if ($this->position && $this->position->legislationItems->isNotEmpty()) {
            foreach ($this->position->legislationItems as $item) {
                if (! empty($item->values)) {
                    return $item;
                }
            }
        }

        // Fallback: vínculo direto no servidor (legado)
        if ($this->legislation_item_id) {
            $item = $this->legislationItem;
            if ($item && ! empty($item->values)) {
                return $item;
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
