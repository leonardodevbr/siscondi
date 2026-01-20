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
    public function execute(): array
    {
        return Cache::remember('dashboard_metrics', 300, function (): array {
            $today = Carbon::today();
            $startOfMonth = Carbon::now()->startOfMonth();

            $salesToday = Sale::query()
                ->where('status', SaleStatus::COMPLETED)
                ->whereDate('created_at', $today)
                ->sum('final_amount');

            $salesMonth = Sale::query()
                ->where('status', SaleStatus::COMPLETED)
                ->where('created_at', '>=', $startOfMonth)
                ->sum('final_amount');

            $totalSalesCountToday = Sale::query()
                ->where('status', SaleStatus::COMPLETED)
                ->whereDate('created_at', $today)
                ->count();

            $lowStockProducts = Product::query()
                ->whereHas('variants.inventories', function ($query): void {
                    $query->whereColumn('inventories.quantity', '<=', 'inventories.min_quantity');
                })
                ->select('id', 'name')
                ->orderBy('id', 'asc')
                ->limit(5)
                ->get()
                ->map(function ($product) {
                    $totalQuantity = Inventory::query()
                        ->whereHas('productVariant', function ($query) use ($product): void {
                            $query->where('product_id', $product->id);
                        })
                        ->sum('quantity');

                    $totalMinQuantity = Inventory::query()
                        ->whereHas('productVariant', function ($query) use ($product): void {
                            $query->where('product_id', $product->id);
                        })
                        ->sum('min_quantity');

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

            $topSellingProducts = SaleItem::query()
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->join('product_variants', 'sale_items.product_variant_id', '=', 'product_variants.id')
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->where('sales.status', SaleStatus::COMPLETED)
                ->where('sales.created_at', '>=', $startOfMonth)
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

            $profitMonth = $this->calculateProfitMonth($startOfMonth);
            $netProfitMonth = $this->calculateNetProfitMonth($startOfMonth, $profitMonth);

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

    private function calculateProfitMonth(Carbon $startOfMonth): float
    {
        $salesItems = SaleItem::query()
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_variants', 'sale_items.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->where('sales.status', SaleStatus::COMPLETED)
            ->where('sales.created_at', '>=', $startOfMonth)
            ->select('sale_items.unit_price', 'sale_items.quantity', 'products.cost_price')
            ->get();

        $totalRevenue = $salesItems->sum(fn ($item) => $item->unit_price * $item->quantity);

        $totalCost = $salesItems->sum(fn ($item) => ($item->cost_price ?? 0) * $item->quantity);

        return (float) ($totalRevenue - $totalCost);
    }

    private function calculateNetProfitMonth(Carbon $startOfMonth, float $profitMonth): float
    {
        $expensesPaid = Expense::query()
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $startOfMonth)
            ->sum('amount');

        return (float) ($profitMonth - $expensesPaid);
    }
}
