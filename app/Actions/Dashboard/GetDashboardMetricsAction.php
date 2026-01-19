<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Enums\SaleStatus;
use App\Models\Product;
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
                ->whereColumn('stock_quantity', '<=', 'min_stock_quantity')
                ->select('id', 'name', 'stock_quantity', 'min_stock_quantity')
                ->orderBy('stock_quantity', 'asc')
                ->orderBy('id', 'asc')
                ->limit(5)
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'stock_quantity' => $product->stock_quantity,
                        'min_stock_quantity' => $product->min_stock_quantity,
                    ];
                });

            $topSellingProducts = SaleItem::query()
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->join('products', 'sale_items.product_id', '=', 'products.id')
                ->where('sales.status', SaleStatus::COMPLETED)
                ->where('sales.created_at', '>=', $startOfMonth)
                ->select('sale_items.product_id', 'products.name', DB::raw('SUM(sale_items.quantity) as total_quantity'))
                ->groupBy('sale_items.product_id', 'products.name')
                ->orderByDesc('total_quantity')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->product_id,
                        'name' => $item->name,
                        'total_quantity' => (int) $item->total_quantity,
                    ];
                });

            $profitMonth = $this->calculateProfitMonth($startOfMonth);

            return [
                'sales_today' => (float) $salesToday,
                'sales_month' => (float) $salesMonth,
                'profit_month' => $profitMonth,
                'total_sales_count_today' => $totalSalesCountToday,
                'low_stock_products' => $lowStockProducts->values()->all(),
                'top_selling_products' => $topSellingProducts->values()->all(),
            ];
        });
    }

    private function calculateProfitMonth(Carbon $startOfMonth): float
    {
        $salesItems = SaleItem::query()
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.status', SaleStatus::COMPLETED)
            ->where('sales.created_at', '>=', $startOfMonth)
            ->select('sale_items.unit_price', 'sale_items.quantity', 'products.cost_price')
            ->get();

        $totalRevenue = $salesItems->sum(fn ($item) => $item->unit_price * $item->quantity);

        $totalCost = $salesItems->sum(fn ($item) => ($item->cost_price ?? 0) * $item->quantity);

        return (float) ($totalRevenue - $totalCost);
    }
}
