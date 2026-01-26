<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 *
 * O nome e category_id costumam ser definidos pelo ProductSeeder (catálogo).
 * Atributos passados em create() sobrescrevem os padrões do definition().
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::query()->inRandomOrder()->value('id')
                ?? Category::factory()->create()->getKey(),
            'supplier_id' => fake()->boolean(70)
                ? Supplier::query()->inRandomOrder()->value('id')
                : null,
            'name' => 'Produto ' . fake()->unique()->word(),
            'description' => fake()->optional(0.7)->sentence(),
            'has_variants' => true,
            'image' => null,
            'cost_price' => fake()->randomFloat(2, 20, 250),
            'sell_price' => fake()->randomFloat(2, 50, 300),
            'composition' => fake()->randomElement([
                '100% Algodão',
                'Algodão com Elastano',
                'Poliester',
                'Viscose',
                'Malha',
                'Jeans',
                'Seda',
                'Linho',
                'Algodão Orgânico',
                'Tecido Misto',
            ]),
        ];
    }
}
