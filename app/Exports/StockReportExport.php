<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockReportExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        private readonly ?int $categoryId = null,
        private readonly ?string $status = null
    ) {
    }

    /**
     * @return Builder<Product>
     */
    public function query(): Builder
    {
        $query = Product::query()
            ->with('category')
            ->orderBy('name', 'asc');

        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }

        if ($this->status === 'low_stock') {
            $query->whereColumn('stock_quantity', '<=', 'min_stock_quantity')
                ->where('stock_quantity', '>', 0);
        } elseif ($this->status === 'out_of_stock') {
            $query->where('stock_quantity', 0);
        }

        return $query;
    }

    /**
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'ID',
            'Nome',
            'SKU',
            'Categoria',
            'Estoque Atual',
            'Estoque Mínimo',
            'Preço Custo',
            'Preço Venda',
        ];
    }

    /**
     * @param Product $product
     * @return array<string|int|float>
     */
    public function map($product): array
    {
        return [
            $product->id,
            $product->name,
            $product->sku ?? 'N/A',
            $product->category?->name ?? 'Sem Categoria',
            $product->stock_quantity,
            $product->min_stock_quantity,
            number_format((float) $product->cost_price, 2, ',', '.'),
            number_format((float) $product->sell_price, 2, ',', '.'),
        ];
    }
}
