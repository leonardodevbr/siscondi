<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CashRegisterStatus;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegister>
 */
class CashRegisterFactory extends Factory
{
    protected $model = CashRegister::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'opened_at' => now(),
            'closed_at' => null,
            'initial_balance' => fake()->randomFloat(2, 0, 1000),
            'final_balance' => null,
            'status' => CashRegisterStatus::OPEN,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CashRegisterStatus::CLOSED,
            'closed_at' => now(),
            'final_balance' => fake()->randomFloat(2, 0, 2000),
        ]);
    }
}
