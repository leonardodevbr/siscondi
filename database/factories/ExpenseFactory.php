<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'paid_at' => null,
            'expense_category_id' => ExpenseCategory::factory(),
            'user_id' => User::factory(),
            'cash_register_transaction_id' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_at' => null,
        ]);
    }
}
