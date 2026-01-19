<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TestProductsExport implements FromArray, WithHeadings
{
    /**
     * @param array<int, array<int, mixed>> $data
     */
    public function __construct(
        private readonly array $data
    ) {
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->data;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'name',
            'sku',
            'category',
            'cost_price',
            'sell_price',
            'stock_quantity',
            'supplier',
        ];
    }
}
