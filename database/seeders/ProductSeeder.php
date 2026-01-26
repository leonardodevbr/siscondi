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
        $variantCounter = 1;

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

                        // Gera código de barras fixo e determinístico
                        $barcode = $this->generateDeterministicBarcode($variantCounter);

                        $variant = $product->variants()->create([
                            'sku' => $sku,
                            'barcode' => $barcode,
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

                        $variantCounter++;
                    }
                }

                $totalProducts++;
            }
        }

        $totalVariants = $totalProducts * 9;
        $this->command->info("Criados {$totalProducts} produtos (catálogo fixo) com 9 variações cada (P/M/G × Preto/Branco/Nude), total de {$totalVariants} variantes, com estoque em {$branches->count()} filial(is).");
    }

    /**
     * Gera código de barras EAN-13 determinístico e fixo baseado no contador.
     * Sempre gera o mesmo código para a mesma variante após reset do banco.
     *
     * Formato: 789XXXXXXXXX + dígito verificador
     * Prefixo 789 = Brasil (fictício para testes)
     */
    private function generateDeterministicBarcode(int $counter): string
    {
        // Prefixo fixo (789 = Brasil) + contador com 9 dígitos
        $prefix = '789';
        $code = $prefix . str_pad((string) $counter, 9, '0', STR_PAD_LEFT);
        
        // Calcula dígito verificador EAN-13
        $checkDigit = $this->calculateEan13CheckDigit($code);
        
        return $code . $checkDigit;
    }

    /**
     * Calcula o dígito verificador para código EAN-13.
     */
    private function calculateEan13CheckDigit(string $code): int
    {
        $sum = 0;
        
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $code[$i];
            // Posições ímpares (0,2,4...) multiplicam por 1, pares (1,3,5...) por 3
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }
        
        $remainder = $sum % 10;
        
        return $remainder === 0 ? 0 : 10 - $remainder;
    }
}
