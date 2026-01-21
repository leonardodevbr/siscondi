<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $colors = ['Azul', 'Vermelho', 'Preto', 'Branco', 'Verde', 'Amarelo', 'Rosa', 'Cinza', 'Marrom', 'Bege'];
        $sizes = ['PP', 'P', 'M', 'G', 'GG', 'XG'];
        $fabrics = ['Algodão', 'Poliester', 'Viscose', 'Malha', 'Jeans', 'Seda', 'Linho'];

        return [
            'product_id' => Product::factory(),
            'sku' => null,
            'barcode' => $this->generateUniqueBarcode(),
            'price' => fake()->boolean(30)
                ? fake()->randomFloat(2, 10, 1000)
                : null,
            'image' => null,
            'attributes' => [
                'cor' => fake()->randomElement($colors),
                'tamanho' => fake()->randomElement($sizes),
                'tecido' => fake()->randomElement($fabrics),
            ],
        ];
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
            $exists = \App\Models\ProductVariant::where('barcode', $barcode)->exists();
            $attempt++;
        } while ($exists && $attempt < $maxAttempts);

        if ($exists) {
            do {
                $barcode = (string) fake()->unique()->numerify('#############');
                $exists = \App\Models\ProductVariant::where('barcode', $barcode)->exists();
            } while ($exists && strlen($barcode) === 13);
        }

        return $barcode;
    }
}
