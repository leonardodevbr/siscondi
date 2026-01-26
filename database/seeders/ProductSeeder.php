<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\SkuGeneratorService;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    private const SIZES = ['P', 'M', 'G'];

    private const COLORS = ['Preto', 'Branco', 'Nude'];

    /**
     * Catálogo: categoria => nomes de produtos
     *
     * @var array<string, list<string>>
     */
    private array $catalog = [
        'Vestidos' => [
            'Vestido indiano',
            'Vestido piriguete',
            'Vestido plus',
            'Vestido',
            'Vestidinho alcinha vestido tomara que caia',
            'Vestido luxo',
        ],
        'Shorts' => [
            'Short alfaiataria Premium',
        ],
        'Calças' => [
            'Calça pantalona alfaiataria',
            'Calça alfaiataria',
        ],
        'Saias' => [
            'Saia tulle',
            'Saia alfaiataria',
            'Saia luxo',
        ],
        'Blusas' => [
            'Blusa social manga comprida',
            'Blusa social manga curta',
            'Blusa regata',
            'Blusinha manga',
            'Blusa plus manga',
            'Blusa alça plus',
            'Cropped',
        ],
        'Macacões' => [
            'Macacão alfaiataria',
            'Macaquinho',
        ],
        'Conjuntos' => [
            'Conjunto luxo',
        ],
    ];

    public function run(): void
    {
        $branches = Branch::all();

        if ($branches->isEmpty()) {
            $this->command->error('Nenhuma filial encontrada. Execute o BranchSeeder primeiro.');

            return;
        }

        /** @var SkuGeneratorService $skuGenerator */
        $skuGenerator = app(SkuGeneratorService::class);

        $totalProducts = 0;

        foreach ($this->catalog as $categoryName => $productNames) {
            $category = Category::firstOrCreate(['name' => $categoryName]);

            foreach ($productNames as $productName) {
                $product = Product::factory()->create([
                    'name' => $productName,
                    'category_id' => $category->id,
                    'has_variants' => true,
                    'sell_price' => fake()->randomFloat(2, 50, 300),
                ]);

                foreach (self::SIZES as $size) {
                    foreach (self::COLORS as $color) {
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
                }

                $totalProducts++;
            }
        }

        $totalVariants = $totalProducts * 9;
        $this->command->info("Criados {$totalProducts} produtos (catálogo fixo) com 9 variações cada (P/M/G × Preto/Branco/Nude), total de {$totalVariants} variantes, com estoque em {$branches->count()} filial(is).");
    }

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
