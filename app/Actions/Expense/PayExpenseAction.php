<?php

declare(strict_types=1);

namespace App\Actions\Expense;

use App\Enums\CashRegisterStatus;
use App\Enums\CashRegisterTransactionType;
use App\Exceptions\NoOpenCashRegisterException;
use App\Models\CashRegister;
use App\Models\CashRegisterTransaction;
use App\Models\Expense;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PayExpenseAction
{
    public function execute(Expense $expense, string $paymentMethod): Expense
    {
        return DB::transaction(function () use ($expense, $paymentMethod): Expense {
            if ($expense->isPaid()) {
                throw new \InvalidArgumentException('Expense is already paid.');
            }

            $cashRegisterTransaction = null;

            // Apenas pagamentos em DINHEIRO deduzem do caixa
            if ($paymentMethod === 'CASH') {
                $user = auth()->user();
                $cashRegister = CashRegister::query()
                    ->where('user_id', $user?->id ?? $expense->user_id)
                    ->where('status', CashRegisterStatus::OPEN)
                    ->first();

                if (! $cashRegister) {
                    throw new NoOpenCashRegisterException('Não há caixa aberto. Abra um caixa para pagar despesas em dinheiro.');
                }

                if ($cashRegister->getCurrentBalance() < (float) $expense->amount) {
                    throw new \InvalidArgumentException('Saldo insuficiente no caixa.');
                }

                $cashRegisterTransaction = $cashRegister->transactions()->create([
                    'type' => CashRegisterTransactionType::BLEED,
                    'amount' => -(float) $expense->amount,
                    'description' => "Despesa: {$expense->description}",
                ]);
            }
            
            // Pagamentos via BANK_TRANSFER (PIX) ou CREDIT_CARD não afetam o caixa

            $expense->update([
                'paid_at' => Carbon::now(),
                'cash_register_transaction_id' => $cashRegisterTransaction?->id,
            ]);

            return $expense->fresh();
        });
    }
}
