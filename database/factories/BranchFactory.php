<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company() . ' - Filial',
            'is_main' => false,
        ];
    }

    /**
     * Indicate that the branch is the main branch.
     */
    public function main(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Matriz',
            'is_main' => true,
        ]);
    }
}
