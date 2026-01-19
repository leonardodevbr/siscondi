<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Expense\CreateExpenseAction;
use App\Actions\Expense\PayExpenseAction;
use App\Exceptions\NoOpenCashRegisterException;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly CreateExpenseAction $createExpenseAction,
        private readonly PayExpenseAction $payExpenseAction
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('financial.manage');

        $query = Expense::query()->with(['category', 'user']);

        if ($request->has('category_id')) {
            $query->where('expense_category_id', $request->integer('category_id'));
        }

        if ($request->has('paid')) {
            $isPaid = $request->boolean('paid');
            if ($isPaid) {
                $query->whereNotNull('paid_at');
            } else {
                $query->whereNull('paid_at');
            }
        }

        if ($request->has('start_date')) {
            $query->whereDate('due_date', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->whereDate('due_date', '<=', $request->input('end_date'));
        }

        $expenses = $query->orderBy('due_date', 'desc')->paginate(15);

        return response()->json($expenses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('financial.manage');

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $expense = $this->createExpenseAction->execute($validated, $request->user());

        return response()->json($expense->load(['category', 'user']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense): JsonResponse
    {
        $this->authorize('financial.manage');

        $expense->load(['category', 'user', 'cashRegisterTransaction']);

        return response()->json($expense);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('financial.manage');

        if ($expense->isPaid()) {
            return response()->json([
                'message' => 'Cannot update a paid expense.',
            ], 400);
        }

        $validated = $request->validate([
            'description' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'due_date' => ['sometimes', 'date'],
            'expense_category_id' => ['sometimes', 'integer', 'exists:expense_categories,id'],
        ]);

        $expense->update($validated);
        $expense->load(['category', 'user']);

        return response()->json($expense);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense): JsonResponse
    {
        $this->authorize('financial.manage');

        if ($expense->isPaid()) {
            return response()->json([
                'message' => 'Cannot delete a paid expense.',
            ], 400);
        }

        $expense->delete();

        return response()->json(null, 204);
    }

    /**
     * Pay an expense.
     */
    public function pay(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('financial.manage');

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:CASH,BANK_TRANSFER,CREDIT_CARD'],
        ]);

        try {
            $expense = $this->payExpenseAction->execute($expense, $validated['payment_method']);

            return response()->json([
                'message' => 'Expense paid successfully',
                'expense' => $expense->load(['category', 'user', 'cashRegisterTransaction']),
            ]);
        } catch (NoOpenCashRegisterException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
