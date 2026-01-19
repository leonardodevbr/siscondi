<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'amount' => fake()->randomFloat(2, 10, 1000),
            'installments' => 1,
            'transaction_id' => null,
            'status' => PaymentStatus::PAID,
        ];
    }

    public function pix(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => PaymentMethod::PIX,
            'status' => PaymentStatus::PENDING,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::PENDING,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::PAID,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::FAILED,
        ]);
    }
}
