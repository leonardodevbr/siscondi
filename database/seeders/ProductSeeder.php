<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\SkuGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        /** @var Collection<int, Branch> $branches */
        $branches = Branch::all();

        if ($branches->isEmpty()) {
            $this->command->error('No branches found. Please run BranchSeeder first.');
            return;
        }

        /** @var SkuGeneratorService $skuGenerator */
        $skuGenerator = app(SkuGeneratorService::class);

        $colors = ['Azul', 'Vermelho', 'Preto', 'Branco', 'Verde', 'Amarelo', 'Rosa', 'Cinza', 'Marrom', 'Bege'];
        $sizes = ['P', 'M', 'G', 'GG'];

        Product::factory(10)->create()->each(function (Product $product) use ($branches, $skuGenerator, $colors, $sizes): void {
            $variantsCount = fake()->numberBetween(3, 5);

            $variants = ProductVariant::factory($variantsCount)->make();

            foreach ($variants as $variant) {
                $variant->product_id = $product->id;

                $attributes = [
                    'cor' => fake()->randomElement($colors),
                    'tamanho' => fake()->randomElement($sizes),
                ];

                $variant->attributes = $attributes;

                $sku = $skuGenerator->generate($product, $attributes);

                $variant->sku = $sku ?? strtoupper(fake()->unique()->bothify('??-#####'));

                $variant->save();

                foreach ($branches as $branch) {
                    Inventory::create([
                        'branch_id' => $branch->id,
                        'product_variant_id' => $variant->id,
                        'quantity' => fake()->numberBetween(10, 100),
                        'min_quantity' => fake()->numberBetween(5, 20),
                    ]);
                }
            }
        });

        $this->command->info("Created 10 products with variants and inventories for {$branches->count()} branches.");
    }
}