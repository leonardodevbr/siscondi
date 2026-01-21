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

        $colors = ['Azul', 'Vermelho', 'Preto', 'Branco', 'Verde', 'Amarelo', 'Rosa', 'Cinza', 'Marrom', 'Bege', 'Laranja', 'Roxo'];
        $sizes = ['P', 'M', 'G'];

        // Produtos COM variações (15 produtos)
        Product::factory(15)->create(['has_variants' => true])->each(function (Product $product) use ($branches, $skuGenerator, $colors, $sizes): void {
            foreach ($sizes as $size) {
                $color = fake()->randomElement($colors);

                $attributes = [
                    'tamanho' => $size,
                    'cor' => $color,
                ];

                $sku = $skuGenerator->generate($product, $attributes);
                if ($sku === null) {
                    $baseName = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $product->name), 0, 6));
                    $colorCode = strtoupper(substr($color, 0, 3));
                    $sku = "{$baseName}-{$colorCode}-{$size}";
                }

                $variant = $product->variants()->create([
                    'sku' => $sku,
                    'barcode' => $this->generateUniqueBarcode(),
                    'price' => null,
                    'image' => null,
                    'attributes' => $attributes,
                ]);

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

        // Produtos SEM variações (5 produtos simples)
        Product::factory(5)->create(['has_variants' => false])->each(function (Product $product) use ($branches, $skuGenerator): void {
            // Cria uma variação padrão (invisível ao usuário)
            $attributes = ['tipo' => 'único'];

            $sku = $skuGenerator->generate($product, $attributes);
            if ($sku === null) {
                $baseName = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $product->name), 0, 8));
                $sku = $baseName . '-' . strtoupper(fake()->bothify('####'));
            }

            $variant = $product->variants()->create([
                'sku' => $sku,
                'barcode' => $this->generateUniqueBarcode(),
                'price' => null,
                'image' => $product->image,
                'attributes' => $attributes,
            ]);

            // Cria estoque para todas as filiais
            foreach ($branches as $branch) {
                Inventory::create([
                    'branch_id' => $branch->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => fake()->numberBetween(10, 100),
                    'min_quantity' => fake()->numberBetween(5, 20),
                ]);
            }
        });

        $this->command->info("Created 15 products WITH variants (P, M, G) and 5 products WITHOUT variants (simple products) with inventories for {$branches->count()} branches.");
    }

    /**
     * Generate a unique EAN13 barcode (13 digits).
     */
    private function generateUniqueBarcode(): string
    {
        $maxAttempts = 100;
        $attempt = 0;

        do {
            $barcode = fake()->ean13();
            $exists = ProductVariant::where('barcode', $barcode)->exists();
            $attempt++;
        } while ($exists && $attempt < $maxAttempts);

        if ($exists) {
            do {
                $barcode = (string) fake()->unique()->numerify('#############');
                $exists = ProductVariant::where('barcode', $barcode)->exists();
            } while ($exists && strlen($barcode) === 13);
        }

        return $barcode;
    }
}