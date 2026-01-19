<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\SalesReportExport;
use App\Exports\StockReportExport;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    /**
     * Get sales report data (JSON).
     */
    public function sales(Request $request): JsonResponse
    {
        $this->authorize('reports.view');

        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'payment_method' => ['nullable', 'string'],
        ]);

        $query = Sale::query()
            ->with(['user', 'customer', 'payments'])
            ->whereDate('created_at', '>=', $request->input('start_date'))
            ->whereDate('created_at', '<=', $request->input('end_date'))
            ->orderBy('created_at', 'desc');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->has('payment_method')) {
            $query->whereHas('payments', function ($q) use ($request) {
                $q->where('method', $request->string('payment_method'));
            });
        }

        $sales = $query->paginate(15);

        $summary = [
            'total_sales' => $sales->total(),
            'total_amount' => (float) $query->sum('final_amount'),
        ];

        return response()->json([
            'data' => $sales,
            'summary' => $summary,
        ]);
    }

    /**
     * Export sales report to Excel.
     */
    public function exportSales(Request $request): BinaryFileResponse
    {
        $this->authorize('reports.view');

        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'payment_method' => ['nullable', 'string'],
        ]);

        $export = new SalesReportExport(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('user_id'),
            $request->input('payment_method')
        );

        $fileName = 'vendas_periodo_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download($export, $fileName);
    }

    /**
     * Get stock report data (JSON).
     */
    public function stock(Request $request): JsonResponse
    {
        $this->authorize('reports.view');

        $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', 'string', 'in:low_stock,out_of_stock,all'],
        ]);

        $query = \App\Models\Product::query()
            ->with('category');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        $status = $request->input('status', 'all');
        if ($status === 'low_stock') {
            $query->whereColumn('stock_quantity', '<=', 'min_stock_quantity')
                ->where('stock_quantity', '>', 0);
        } elseif ($status === 'out_of_stock') {
            $query->where('stock_quantity', 0);
        }

        $products = $query->orderBy('name', 'asc')->paginate(15);

        return response()->json($products);
    }

    /**
     * Export stock report to Excel.
     */
    public function exportStock(Request $request): BinaryFileResponse
    {
        $this->authorize('reports.view');

        $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', 'string', 'in:low_stock,out_of_stock,all'],
        ]);

        $export = new StockReportExport(
            $request->input('category_id'),
            $request->input('status')
        );

        $fileName = 'estoque_atual_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download($export, $fileName);
    }
}
