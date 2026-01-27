<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Enums\SaleStatus;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GetDashboardMetricsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(?int $branchId = null): array
    {
        $cacheKey = 'dashboard_metrics_'.($branchId ?? 'all');
        
        return Cache::remember($cacheKey, 300, function () use ($branchId): array {
            $today = Carbon::today();
            $startOfMonth = Carbon::now()->startOfMonth();

            // Query base para vendas
            $salesQuery = Sale::query()->where('status', SaleStatus::COMPLETED);
            if ($branchId) {
                $salesQuery->where('branch_id', $branchId);
            }

            $salesToday = (clone $salesQuery)
                ->whereDate('created_at', $today)
                ->sum('final_amount');

            $salesMonth = (clone $salesQuery)
                ->where('created_at', '>=', $startOfMonth)
                ->sum('final_amount');

            $totalSalesCountToday = (clone $salesQuery)
                ->whereDate('created_at', $today)
                ->count();

            // Produtos em baixo estoque (filtrado por filial)
            $lowStockProducts = Product::query()
                ->whereHas('variants.inventories', function ($query) use ($branchId): void {
                    $query->whereColumn('inventories.quantity', '<=', 'inventories.min_quantity');
                    if ($branchId) {
                        $query->where('inventories.branch_id', $branchId);
                    }
                })
                ->select('id', 'name')
                ->orderBy('id', 'asc')
                ->limit(5)
                ->get()
                ->map(function ($product) use ($branchId) {
                    $inventoryQuery = Inventory::query()
                        ->whereHas('productVariant', function ($query) use ($product): void {
                            $query->where('product_id', $product->id);
                        });
                    
                    if ($branchId) {
                        $inventoryQuery->where('branch_id', $branchId);
                    }

                    $totalQuantity = (clone $inventoryQuery)->sum('quantity');
                    $totalMinQuantity = (clone $inventoryQuery)->sum('min_quantity');

                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'stock_quantity' => $totalQuantity,
                        'min_stock_quantity' => $totalMinQuantity,
                    ];
                })
                ->filter(function ($product) {
                    return $product['stock_quantity'] <= $product['min_stock_quantity'];
                })
                ->values();

            // Top produtos (filtrado por filial)
            $topSellingQuery = SaleItem::query()
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->join('product_variants', 'sale_items.product_variant_id', '=', 'product_variants.id')
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->where('sales.status', SaleStatus::COMPLETED)
                ->where('sales.created_at', '>=', $startOfMonth);
            
            if ($branchId) {
                $topSellingQuery->where('sales.branch_id', $branchId);
            }

            $topSellingProducts = $topSellingQuery
                ->select('products.id', 'products.name', DB::raw('SUM(sale_items.quantity) as total_quantity'))
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total_quantity')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'total_quantity' => (int) $item->total_quantity,
                    ];
                });

            $profitMonth = $this->calculateProfitMonth($startOfMonth, $branchId);
            $netProfitMonth = $this->calculateNetProfitMonth($startOfMonth, $profitMonth, $branchId);

            return [
                'sales_today' => (float) $salesToday,
                'sales_month' => (float) $salesMonth,
                'profit_month' => $profitMonth,
                'net_profit_month' => $netProfitMonth,
                'total_sales_count_today' => $totalSalesCountToday,
                'low_stock_products' => $lowStockProducts->all(),
                'top_selling_products' => $topSellingProducts->values()->all(),
            ];
        });
    }

    private function calculateProfitMonth(Carbon $startOfMonth, ?int $branchId = null): float
    {
        $query = SaleItem::query()
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_variants', 'sale_items.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->where('sales.status', SaleStatus::COMPLETED)
            ->where('sales.created_at', '>=', $startOfMonth);
        
        if ($branchId) {
            $query->where('sales.branch_id', $branchId);
        }

        $salesItems = $query
            ->select('sale_items.unit_price', 'sale_items.quantity', 'products.cost_price')
            ->get();

        $totalRevenue = $salesItems->sum(fn ($item) => $item->unit_price * $item->quantity);

        $totalCost = $salesItems->sum(fn ($item) => ($item->cost_price ?? 0) * $item->quantity);

        return (float) ($totalRevenue - $totalCost);
    }

    private function calculateNetProfitMonth(Carbon $startOfMonth, float $profitMonth, ?int $branchId = null): float
    {
        // NOTA: Despesas não são filtradas por filial pois a tabela 'expenses' não possui 'branch_id'
        // Se o sistema tiver múltiplas filiais, considere adicionar essa coluna no futuro
        $expensesPaid = Expense::query()
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $startOfMonth)
            ->sum('amount');

        return (float) ($profitMonth - $expensesPaid);
    }
}
