<?php

declare(strict_types=1);

namespace App\Enums;

enum DailyRequestStatus: string
{
    case DRAFT = 'draft';
    case REQUESTED = 'requested';
    case VALIDATED = 'validated';
    case AUTHORIZED = 'authorized';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    /**
     * Retorna o label em português
     */
    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Rascunho',
            self::REQUESTED => 'Solicitado',
            self::VALIDATED => 'Validado',
            self::AUTHORIZED => 'Concedido',
            self::PAID => 'Pago',
            self::CANCELLED => 'Cancelado',
        };
    }

    /**
     * Retorna a cor para exibição no frontend
     */
    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::REQUESTED => 'blue',
            self::VALIDATED => 'yellow',
            self::AUTHORIZED => 'green',
            self::PAID => 'purple',
            self::CANCELLED => 'red',
        };
    }

    /**
     * Verifica se pode avançar para o próximo status
     */
    public function canTransitionTo(self $newStatus): bool
    {
        return match($this) {
            self::DRAFT => in_array($newStatus, [self::REQUESTED, self::CANCELLED]),
            self::REQUESTED => in_array($newStatus, [self::VALIDATED, self::CANCELLED]),
            self::VALIDATED => in_array($newStatus, [self::AUTHORIZED, self::CANCELLED]),
            self::AUTHORIZED => in_array($newStatus, [self::PAID, self::CANCELLED]),
            self::PAID => false, // Não pode mudar depois de pago
            self::CANCELLED => false, // Não pode mudar depois de cancelado
        };
    }
}
