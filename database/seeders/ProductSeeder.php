<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $mainBranch = Branch::where('is_main', true)->first();

        if (! $mainBranch) {
            $this->command->error('Main branch not found. Please run BranchSeeder first.');
            return;
        }

        Product::factory(10)->create()->each(function (Product $product) use ($mainBranch): void {
            $variantsCount = fake()->numberBetween(3, 5);

            ProductVariant::factory($variantsCount)
                ->for($product)
                ->create()
                ->each(function (ProductVariant $variant) use ($mainBranch): void {
                    Inventory::create([
                        'branch_id' => $mainBranch->id,
                        'product_variant_id' => $variant->id,
                        'quantity' => fake()->numberBetween(10, 100),
                        'min_quantity' => fake()->numberBetween(5, 20),
                    ]);
                });
        });

        $this->command->info('Created 10 products with variants and inventories.');
    }
}
