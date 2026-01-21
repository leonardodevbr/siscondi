<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
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
            'category_id' => Category::query()->inRandomOrder()->value('id'),
            'supplier_id' => fake()->boolean(70)
                ? Supplier::query()->inRandomOrder()->value('id')
                : null,
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(10),
            'cost_price' => fake()->randomFloat(2, 1, 500),
            'sell_price' => fake()->randomFloat(2, 1, 800),
            'composition' => fake()->randomElement(['Algodão', 'Poliester', 'Viscose', 'Malha', 'Jeans', 'Seda', 'Linho']),
        ];
    }
}

