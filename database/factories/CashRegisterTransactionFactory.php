<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CashRegisterTransactionType;
use App\Models\CashRegister;
use App\Models\CashRegisterTransaction;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegisterTransaction>
 */
class CashRegisterTransactionFactory extends Factory
{
    protected $model = CashRegisterTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_register_id' => CashRegister::factory(),
            'type' => fake()->randomElement(CashRegisterTransactionType::cases()),
            'amount' => fake()->randomFloat(2, 1, 500),
            'description' => fake()->optional()->sentence(),
            'sale_id' => null,
        ];
    }

    public function sale(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CashRegisterTransactionType::SALE,
            'sale_id' => Sale::factory(),
        ]);
    }
}
