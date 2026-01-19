<?php

declare(strict_types=1);

namespace App\Actions\CashRegister;

use App\Enums\CashRegisterStatus;
use App\Enums\CashRegisterTransactionType;
use App\Models\CashRegister;
use Illuminate\Support\Facades\DB;

class CloseCashRegisterAction
{
    /**
     * @throws \InvalidArgumentException
     */
    public function execute(CashRegister $cashRegister, float $finalBalance): CashRegister
    {
        if ($cashRegister->status !== CashRegisterStatus::OPEN) {
            throw new \InvalidArgumentException('Caixa não está aberto.');
        }

        return DB::transaction(function () use ($cashRegister, $finalBalance): CashRegister {
            $cashRegister->transactions()->create([
                'type' => CashRegisterTransactionType::CLOSING_BALANCE,
                'amount' => 0,
                'description' => 'Fechamento do caixa',
            ]);

            $cashRegister->update([
                'closed_at' => now(),
                'final_balance' => $finalBalance,
                'status' => CashRegisterStatus::CLOSED,
            ]);

            return $cashRegister->fresh();
        });
    }
}
