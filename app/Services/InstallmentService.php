<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

class InstallmentService
{
    /**
     * Calcula opções de parcelamento para cartão de crédito (manual).
     * Regras de loja: cc_max_installments, cc_no_interest_installments, cc_interest_rate, cc_min_installment_value.
     *
     * @return list<array{installment: int, amount: float, total: float, interest_free: bool}>
     */
    public function calculate(float $totalAmount): array
    {
        $max = (int) (Setting::get('cc_max_installments') ?? 12);
        $noInterest = (int) (Setting::get('cc_no_interest_installments') ?? 3);
        $rate = (float) (Setting::get('cc_interest_rate') ?? 2.99) / 100;
        $minInstallment = (float) (Setting::get('cc_min_installment_value') ?? 10.0);

        $options = [];
        for ($t = 1; $t <= $max; $t++) {
            $interestFree = $t <= $noInterest;
            if ($interestFree) {
                $total = $totalAmount;
                $amount = round($total / $t, 2);
            } else {
                $total = $totalAmount * ((1 + $rate) ** $t);
                $total = round($total, 2);
                $amount = round($total / $t, 2);
            }
            if ($amount < $minInstallment) {
                continue;
            }
            $options[] = [
                'installment' => $t,
                'amount' => $amount,
                'total' => $total,
                'interest_free' => $interestFree,
            ];
        }

        return $options;
    }
}
