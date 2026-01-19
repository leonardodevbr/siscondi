<?php

declare(strict_types=1);

namespace App\Actions\CashRegister;

use App\Enums\CashRegisterStatus;
use App\Enums\CashRegisterTransactionType;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OpenCashRegisterAction
{
    /**
     * @throws \InvalidArgumentException
     */
    public function execute(User $user, float $initialBalance): CashRegister
    {
        $openRegister = CashRegister::where('user_id', $user->id)
            ->where('status', CashRegisterStatus::OPEN)
            ->first();

        if ($openRegister) {
            throw new \InvalidArgumentException('Usuário já possui um caixa aberto.');
        }

        return DB::transaction(function () use ($user, $initialBalance): CashRegister {
            $cashRegister = CashRegister::create([
                'user_id' => $user->id,
                'opened_at' => now(),
                'initial_balance' => $initialBalance,
                'status' => CashRegisterStatus::OPEN,
            ]);

            $cashRegister->transactions()->create([
                'type' => CashRegisterTransactionType::OPENING_BALANCE,
                'amount' => $initialBalance,
                'description' => 'Saldo inicial do caixa',
            ]);

            return $cashRegister;
        });
    }
}
