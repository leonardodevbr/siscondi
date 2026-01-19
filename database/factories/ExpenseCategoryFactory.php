<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Aluguel', 'Pessoal', 'Fornecedores', 'Energia', 'Água', 'Telefone', 'Internet', 'Marketing', 'Manutenção']),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
