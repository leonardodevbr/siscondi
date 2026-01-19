<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CashRegister\AddCashMovementAction;
use App\Actions\CashRegister\CloseCashRegisterAction;
use App\Actions\CashRegister\OpenCashRegisterAction;
use App\Enums\CashRegisterStatus;
use App\Enums\CashRegisterTransactionType;
use App\Models\CashRegister;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CashRegisterController extends Controller
{
    public function __construct(
        private readonly OpenCashRegisterAction $openCashRegisterAction,
        private readonly CloseCashRegisterAction $closeCashRegisterAction,
        private readonly AddCashMovementAction $addCashMovementAction
    ) {
    }

    /**
     * Open cash register.
     */
    public function open(Request $request): JsonResponse
    {
        $this->authorize('pos.access');

        $validator = Validator::make($request->all(), [
            'initial_balance' => ['required', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $cashRegister = $this->openCashRegisterAction->execute(
                $request->user(),
                (float) $request->input('initial_balance')
            );

            return response()->json([
                'message' => 'Cash register opened successfully',
                'cash_register' => $cashRegister->load(['user', 'transactions']),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Close cash register.
     */
    public function close(Request $request, CashRegister $cashRegister): JsonResponse
    {
        $this->authorize('pos.access');

        $validator = Validator::make($request->all(), [
            'final_balance' => ['required', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $cashRegister = $this->closeCashRegisterAction->execute(
                $cashRegister,
                (float) $request->input('final_balance')
            );

            return response()->json([
                'message' => 'Cash register closed successfully',
                'cash_register' => $cashRegister->load(['user', 'transactions']),
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get cash register status.
     */
    public function status(Request $request): JsonResponse
    {
        $this->authorize('pos.access');

        $cashRegister = CashRegister::where('user_id', $request->user()->id)
            ->where('status', CashRegisterStatus::OPEN)
            ->with(['transactions'])
            ->first();

        if (! $cashRegister) {
            return response()->json([
                'is_open' => false,
                'message' => 'No open cash register',
            ], 200);
        }

        $currentBalance = $cashRegister->getCurrentBalance();

        return response()->json([
            'is_open' => true,
            'cash_register' => [
                'id' => $cashRegister->id,
                'opened_at' => $cashRegister->opened_at,
                'initial_balance' => $cashRegister->initial_balance,
                'current_balance' => $currentBalance,
                'transactions_count' => $cashRegister->transactions()->count(),
            ],
        ], 200);
    }

    /**
     * Add cash movement (supply or bleed).
     */
    public function movement(Request $request, CashRegister $cashRegister): JsonResponse
    {
        $this->authorize('financial.manage');

        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'in:supply,bleed'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $type = $request->input('type') === 'supply'
                ? CashRegisterTransactionType::SUPPLY
                : CashRegisterTransactionType::BLEED;

            $transaction = $this->addCashMovementAction->execute(
                $cashRegister,
                $type,
                (float) $request->input('amount'),
                $request->input('description')
            );

            return response()->json([
                'message' => 'Cash movement added successfully',
                'transaction' => $transaction->load('cashRegister'),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
