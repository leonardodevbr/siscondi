<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;

trait ProductTestHelper
{
    /**
     * Cria um produto completo com variação e inventário na branch principal
     */
    protected function createProductWithVariant(array $productAttributes = [], array $variantAttributes = [], int $quantity = 100): ProductVariant
    {
        $mainBranch = Branch::where('is_main', true)->first();

        if (! $mainBranch) {
            $mainBranch = Branch::factory()->create(['name' => 'Matriz', 'is_main' => true]);
        }

        $product = Product::factory()->create($productAttributes);
        $variant = ProductVariant::factory()->for($product)->create($variantAttributes);

        Inventory::create([
            'branch_id' => $mainBranch->id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'min_quantity' => 10,
        ]);

        return $variant;
    }

    /**
     * Cria múltiplos produtos com variações e inventários
     *
     * @return array<ProductVariant>
     */
    protected function createProductsWithVariants(int $count = 1, array $productAttributes = [], int $quantity = 100): array
    {
        $variants = [];

        for ($i = 0; $i < $count; $i++) {
            $variants[] = $this->createProductWithVariant($productAttributes, [], $quantity);
        }

        return $variants;
    }
}
