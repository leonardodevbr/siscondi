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
            'sku' => strtoupper(fake()->unique()->bothify('VAR-#####')),
            'barcode' => fake()->ean13(),
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
}
