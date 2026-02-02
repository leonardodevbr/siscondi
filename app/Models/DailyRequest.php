<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DailyRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyRequest extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'servant_id',
        'destination_type',
        'legislation_item_snapshot_id',
        'destination_city',
        'destination_state',
        'departure_date',
        'return_date',
        'purpose',
        'reason',
        'quantity_days',
        'unit_value',
        'total_value',
        'status',
        'requester_id',
        'validator_id',
        'authorizer_id',
        'payer_id',
        'validated_at',
        'authorized_at',
        'paid_at',
    ];

    /**
     * unit_value e total_value em centavos (integer).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'departure_date' => 'date',
        'return_date' => 'date',
        'quantity_days' => 'decimal:1',
        'unit_value' => 'integer',
        'total_value' => 'integer',
        'status' => DailyRequestStatus::class,
        'validated_at' => 'datetime',
        'authorized_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /**
     * Servidor que solicitou a diária
     * 
     * @return BelongsTo<Servant, DailyRequest>
     */
    public function servant(): BelongsTo
    {
        return $this->belongsTo(Servant::class);
    }

    /**
     * Item da legislação (snapshot) no momento da solicitação
     *
     * @return BelongsTo<LegislationItem, DailyRequest>
     */
    public function legislationItemSnapshot(): BelongsTo
    {
        return $this->belongsTo(LegislationItem::class, 'legislation_item_snapshot_id');
    }

    /**
     * Usuário que criou a solicitação
     * 
     * @return BelongsTo<User, DailyRequest>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Secretário que validou
     * 
     * @return BelongsTo<User, DailyRequest>
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id');
    }

    /**
     * Prefeito que concedeu/autorizou
     * 
     * @return BelongsTo<User, DailyRequest>
     */
    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorizer_id');
    }

    /**
     * Tesoureiro que efetuou o pagamento
     * 
     * @return BelongsTo<User, DailyRequest>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    /**
     * Linha do tempo / auditoria de cada ação na solicitação
     *
     * @return HasMany<DailyRequestLog, DailyRequest>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(DailyRequestLog::class);
    }

    /**
     * Verifica se o PDF pode ser gerado (apenas após assinatura do prefeito/concedente).
     */
    public function canGeneratePdf(): bool
    {
        return in_array($this->status, [DailyRequestStatus::AUTHORIZED, DailyRequestStatus::PAID]);
    }

    /**
     * Calcula o valor total baseado na quantidade de dias e valor unitário (tudo em centavos).
     */
    public function calculateTotal(): void
    {
        $this->total_value = (int) round((float) $this->quantity_days * (int) $this->unit_value);
    }

    /**
     * Verifica se a solicitação pode ser editada
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [DailyRequestStatus::DRAFT, DailyRequestStatus::REQUESTED]);
    }

    /**
     * Verifica se a solicitação pode ser cancelada
     */
    public function isCancellable(): bool
    {
        return $this->status !== DailyRequestStatus::PAID && $this->status !== DailyRequestStatus::CANCELLED;
    }
}
