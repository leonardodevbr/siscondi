<?php

declare(strict_types=1);

namespace App\Actions\Expense;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateExpenseAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data, User $user): Expense
    {
        return DB::transaction(function () use ($data, $user): Expense {
            $paidAt = isset($data['paid_at']) && $data['paid_at'] 
                ? Carbon::parse($data['paid_at']) 
                : null;

            return Expense::create([
                'description' => $data['description'],
                'amount' => (float) $data['amount'],
                'due_date' => Carbon::parse($data['due_date']),
                'paid_at' => $paidAt,
                'expense_category_id' => $data['expense_category_id'],
                'user_id' => $user->id,
            ]);
        });
    }
}
