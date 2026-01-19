<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\Product;
use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure
{
    private ImportBatch $batch;

    public function __construct(ImportBatch $batch)
    {
        $this->batch = $batch;
    }
    /**
     * @param array<string, mixed> $row
     */
    public function model(array $row): ?Product
    {
        if (empty($row['name']) || empty($row['sku']) || empty($row['category'])) {
            return null;
        }

        $categoryName = trim((string) ($row['category'] ?? ''));
        $category = Category::firstOrCreate(
            ['name' => $categoryName],
            [
                'description' => null,
                'active' => true,
            ]
        );

        $supplier = null;
        $supplierName = trim((string) ($row['supplier'] ?? ''));
        if (! empty($supplierName)) {
            $supplier = Supplier::where('name', $supplierName)->first();
            
            if (! $supplier) {
                $cnpjBase = abs(crc32($supplierName));
                $cnpj = '00.' . str_pad((string) ($cnpjBase % 1000), 3, '0', STR_PAD_LEFT) . 
                        '.' . str_pad((string) (($cnpjBase / 1000) % 1000), 3, '0', STR_PAD_LEFT) . 
                        '/0001-' . str_pad((string) ($cnpjBase % 100), 2, '0', STR_PAD_LEFT);
                
                while (Supplier::where('cnpj', $cnpj)->exists()) {
                    $cnpjBase++;
                    $cnpj = '00.' . str_pad((string) ($cnpjBase % 1000), 3, '0', STR_PAD_LEFT) . 
                            '.' . str_pad((string) (($cnpjBase / 1000) % 1000), 3, '0', STR_PAD_LEFT) . 
                            '/0001-' . str_pad((string) ($cnpjBase % 100), 2, '0', STR_PAD_LEFT);
                }
                
                $supplier = Supplier::create([
                    'name' => $supplierName,
                    'trade_name' => $supplierName,
                    'cnpj' => $cnpj,
                    'active' => true,
                ]);
            }
        }

        $sku = trim((string) ($row['sku'] ?? ''));

        $costPriceValue = $row['cost_price'] ?? 0;
        $sellPriceValue = $row['sell_price'] ?? 0;
        $stockQuantityValue = $row['stock_quantity'] ?? 0;
        $minStockQuantityValue = $row['min_stock_quantity'] ?? 0;

        $costPrice = is_numeric($costPriceValue) ? (float) $costPriceValue : 0.0;
        $sellPrice = is_numeric($sellPriceValue) ? (float) $sellPriceValue : 0.0;
        $stockQuantity = is_numeric($stockQuantityValue) ? (int) $stockQuantityValue : 0;
        $minStockQuantity = is_numeric($minStockQuantityValue) ? (int) $minStockQuantityValue : 0;

        $product = Product::updateOrCreate(
            ['sku' => $sku],
            [
                'category_id' => $category->id,
                'supplier_id' => $supplier?->id,
                'name' => trim((string) ($row['name'] ?? '')),
                'cost_price' => $costPrice,
                'sell_price' => $sellPrice,
                'stock_quantity' => $stockQuantity,
                'min_stock_quantity' => $minStockQuantity,
            ]
        );

        $this->batch->increment('success_count');

        return $product;
    }

    /**
     * @param Failure ...$failures
     */
    public function onFailure(Failure ...$failures): void
    {
        $this->batch->refresh();
        
        foreach ($failures as $failure) {
            $this->batch->increment('error_count');
            
            $errors = $this->batch->errors ?? [];
            $errors[] = [
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
                'values' => $failure->values(),
            ];
            
            $this->batch->update(['errors' => $errors]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'sku' => ['required'],
            'category' => ['required'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable'],
        ];
    }
}
