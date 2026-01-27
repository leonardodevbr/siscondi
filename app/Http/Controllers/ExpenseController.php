<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Expense\CreateExpenseAction;
use App\Actions\Expense\PayExpenseAction;
use App\Exceptions\NoOpenCashRegisterException;
use App\Http\Requests\PayExpenseRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
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

        $user = $request->user();
        
        // Determina a filial ativa
        $activeBranchId = $request->header('X-Branch-ID') 
            ? (int) $request->header('X-Branch-ID') 
            : $user->branch_id;

        $query = Expense::query()->with(['category', 'user', 'branch']);

        // Filtro por filial (respeita roles)
        if ($user->hasRole('super-admin')) {
            // Super Admin pode ver despesas de todas as filiais ou filtrar por uma específica
            if ($activeBranchId) {
                $query->where('branch_id', $activeBranchId);
            }
        } else {
            // Gerentes e outros veem apenas despesas de sua filial
            $query->where('branch_id', $activeBranchId ?? $user->branch_id);
        }

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

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('description', 'like', "%{$search}%");
        }

        $expenses = $query->latest('due_date')->paginate(15);

        return ExpenseResource::collection($expenses)->response();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        
        // Adiciona branch_id automaticamente
        $activeBranchId = $request->header('X-Branch-ID') 
            ? (int) $request->header('X-Branch-ID') 
            : $user->branch_id;
            
        $validated['branch_id'] = $activeBranchId;
        
        $expense = $this->createExpenseAction->execute($validated, $user);
        $expense->load(['category', 'user', 'branch']);

        return response()->json(new ExpenseResource($expense), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense): JsonResponse
    {
        $this->authorize('financial.manage');

        $expense->load(['category', 'user', 'cashRegisterTransaction']);

        return response()->json(new ExpenseResource($expense));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        if ($expense->isPaid()) {
            return response()->json([
                'message' => 'Cannot update a paid expense.',
            ], 400);
        }

        $expense->update($request->validated());
        $expense->load(['category', 'user']);

        return response()->json(new ExpenseResource($expense));
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
    public function pay(PayExpenseRequest $request, Expense $expense): JsonResponse
    {
        try {
            $expense = $this->payExpenseAction->execute($expense, $request->validated()['payment_method']);
            $expense->load(['category', 'user', 'cashRegisterTransaction']);

            return response()->json([
                'message' => 'Expense paid successfully',
                'expense' => new ExpenseResource($expense),
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
