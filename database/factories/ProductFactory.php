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
     * Nomes de produtos de moda
     */
    private array $productNames = [
        'Camiseta', 'Vestido', 'Jaqueta', 'Calça', 'Shorts', 'Saia', 'Blusa', 'Camisa',
        'Regata', 'Moletom', 'Cardigan', 'Blazer', 'Macacão', 'Top', 'Legging', 'Bermuda',
    ];

    /**
     * Marcas fictícias
     */
    private array $brands = [
        'ModaStyle', 'UrbanWear', 'FashionLab', 'StyleCo', 'Trendy', 'ChicMode', 'EliteFashion', 'ModernWear',
    ];

    /**
     * Descrições de moda
     */
    private array $descriptions = [
        'Peça versátil e confortável, perfeita para o dia a dia.',
        'Design moderno e elegante, ideal para ocasiões especiais.',
        'Conforto e estilo em uma única peça.',
        'Tecido de alta qualidade com acabamento impecável.',
        'Corte que valoriza a silhueta, disponível em vários tamanhos.',
        'Peça essencial para compor looks casuais e sofisticados.',
        'Combinação perfeita entre conforto e tendência.',
        'Estilo único que se adapta a diferentes ocasiões.',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $productName = fake()->randomElement($this->productNames);
        $brand = fake()->randomElement($this->brands);
        $fullName = "{$productName} {$brand}";

        return [
            'category_id' => Category::query()->inRandomOrder()->value('id'),
            'supplier_id' => fake()->boolean(70)
                ? Supplier::query()->inRandomOrder()->value('id')
                : null,
            'name' => $fullName,
            'description' => fake()->randomElement($this->descriptions),
            'has_variants' => true,
            'image' => null,
            'cost_price' => fake()->randomFloat(2, 20, 300),
            'sell_price' => fake()->randomFloat(2, 50, 600),
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

