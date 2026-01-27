<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Sales\CreateSaleAction;
use App\Enums\SaleStatus;
use App\Exceptions\InvalidCouponException;
use App\Exceptions\NoOpenCashRegisterException;
use App\Exports\SalesExport;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SaleController extends Controller
{
    public function __construct(
        private readonly CreateSaleAction $createSaleAction
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('pos.access');

        $user = $request->user();
        $query = Sale::query()->with(['user', 'customer', 'coupon', 'items.productVariant.product', 'payments', 'branch']);

        // Obter filial ativa do header ou do usuário
        $activeBranchId = $request->header('X-Branch-Id') 
            ? (int) $request->header('X-Branch-Id') 
            : $user->branch_id;

        // Aplicar regras de visibilidade baseadas no cargo
        if ($user->hasRole('super-admin')) {
            // Super Admin vê vendas da filial ativa/selecionada
            if ($activeBranchId) {
                $query->where('branch_id', $activeBranchId);
            }
            // Se não houver filial ativa, pode filtrar por branch_id via query param
            elseif ($request->has('branch_id')) {
                $query->where('branch_id', $request->integer('branch_id'));
            }
        } elseif ($user->hasRole('manager')) {
            // Gerente vê apenas vendas da filial ativa ou da sua filial
            $branchId = $activeBranchId ?? $user->branch_id;
            $query->where('branch_id', $branchId);
        } else {
            // Vendedor/Operador vê apenas suas próprias vendas da filial ativa
            $query->where('user_id', $user->id);
            if ($activeBranchId) {
                $query->where('branch_id', $activeBranchId);
            }
        }

        // Filtros adicionais
        if ($request->has('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        if ($request->has('user_id') && $user->hasRole(['super-admin', 'manager'])) {
            $query->where('user_id', $request->integer('user_id'));
        }

        // Busca por ID
        if ($request->has('id')) {
            $query->where('id', $request->integer('id'));
        }

        // Busca por nome do cliente
        if ($request->has('customer_name')) {
            $searchTerm = $request->string('customer_name')->trim();
            $query->whereHas('customer', function ($q) use ($searchTerm): void {
                $q->where('name', 'like', "%{$searchTerm}%");
            });
        }

        // Filtro por data
        if ($request->has('date')) {
            $date = $request->string('date');
            $query->whereDate('created_at', $date);
        }

        $sales = $query->latest('created_at')->paginate(15);

        return SaleResource::collection($sales)->response();
    }

    /**
     * Export sales to Excel.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('pos.access');

        $user = $request->user();

        if (! $user->hasRole(['super-admin', 'manager'])) {
            abort(403, 'Você não tem permissão para exportar vendas.');
        }

        $query = Sale::query()->with(['user', 'customer', 'branch', 'payments']);

        // Obter filial ativa do header ou do usuário
        $activeBranchId = $request->header('X-Branch-ID') 
            ? (int) $request->header('X-Branch-ID') 
            : $user->branch_id;

        // Aplicar mesmas regras de visibilidade
        if ($user->hasRole('super-admin')) {
            // Super Admin exporta vendas da filial ativa/selecionada
            if ($activeBranchId) {
                $query->where('branch_id', $activeBranchId);
            }
            // Se não houver filial ativa, pode filtrar por branch_id via query param
            elseif ($request->has('branch_id')) {
                $query->where('branch_id', $request->integer('branch_id'));
            }
        } elseif ($user->hasRole('manager')) {
            // Gerente exporta vendas da filial ativa ou da sua filial
            $branchId = $activeBranchId ?? $user->branch_id;
            $query->where('branch_id', $branchId);
        }

        // Aplicar mesmos filtros
        if ($request->has('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->has('id')) {
            $query->where('id', $request->integer('id'));
        }

        if ($request->has('customer_name')) {
            $searchTerm = $request->string('customer_name')->trim();
            $query->whereHas('customer', function ($q) use ($searchTerm): void {
                $q->where('name', 'like', "%{$searchTerm}%");
            });
        }

        if ($request->has('date')) {
            $date = $request->string('date');
            $query->whereDate('created_at', $date);
        }

        $sales = $query->latest('created_at')->get();

        return Excel::download(new SalesExport($sales), 'vendas.xlsx');
    }

    /**
     * Store a newly created sale.
     */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        try {
            $sale = $this->createSaleAction->execute(
                $request->validated(),
                $request->user()
            );

            return response()->json(new SaleResource($sale), 201);
        } catch (NoOpenCashRegisterException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        } catch (InvalidCouponException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Sale $sale): JsonResponse
    {
        $this->authorize('pos.access');

        $sale->load(['user', 'customer', 'coupon', 'items.productVariant.product', 'payments', 'branch']);

        return response()->json(new SaleResource($sale));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSaleRequest $request, Sale $sale): JsonResponse
    {
        $sale->update($request->validated());
        $sale->load(['user', 'customer', 'coupon', 'items.productVariant.product', 'payments', 'branch']);

        return response()->json(new SaleResource($sale));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sale $sale): JsonResponse
    {
        $this->authorize('pos.discount');

        $sale->update(['status' => SaleStatus::CANCELED]);

        return response()->json(new SaleResource($sale));
    }
}
