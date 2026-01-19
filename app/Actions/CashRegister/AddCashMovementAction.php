<?php

declare(strict_types=1);

namespace App\Actions\CashRegister;

use App\Enums\CashRegisterStatus;
use App\Enums\CashRegisterTransactionType;
use App\Models\CashRegister;
use App\Models\CashRegisterTransaction;
use Illuminate\Support\Facades\DB;

class AddCashMovementAction
{
    /**
     * @throws \InvalidArgumentException
     */
    public function execute(
        CashRegister $cashRegister,
        CashRegisterTransactionType $type,
        float $amount,
        ?string $description = null
    ): CashRegisterTransaction {
        if ($cashRegister->status !== CashRegisterStatus::OPEN) {
            throw new \InvalidArgumentException('Caixa não está aberto.');
        }

        if ($type === CashRegisterTransactionType::BLEED) {
            $currentBalance = $cashRegister->getCurrentBalance();
            if ($currentBalance < $amount) {
                throw new \InvalidArgumentException('Saldo insuficiente para realizar a sangria.');
            }
            $amount = -abs($amount);
        } elseif ($type === CashRegisterTransactionType::SUPPLY) {
            $amount = abs($amount);
        }

        return DB::transaction(function () use ($cashRegister, $type, $amount, $description): CashRegisterTransaction {
            return $cashRegister->transactions()->create([
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
            ]);
        });
    }
}
