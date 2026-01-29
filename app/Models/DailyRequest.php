<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DailyRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyRequest extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'servant_id',
        'legislation_snapshot_id',
        'destination_city',
        'destination_state',
        'departure_date',
        'return_date',
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
     * @var array<string, string>
     */
    protected $casts = [
        'departure_date' => 'date',
        'return_date' => 'date',
        'quantity_days' => 'decimal:1',
        'unit_value' => 'decimal:2',
        'total_value' => 'decimal:2',
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
     * Legislação (snapshot) usada no momento da solicitação
     * 
     * @return BelongsTo<Legislation, DailyRequest>
     */
    public function legislationSnapshot(): BelongsTo
    {
        return $this->belongsTo(Legislation::class, 'legislation_snapshot_id');
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
     * Calcula o valor total baseado na quantidade de dias e valor unitário
     */
    public function calculateTotal(): void
    {
        $this->total_value = $this->quantity_days * $this->unit_value;
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
