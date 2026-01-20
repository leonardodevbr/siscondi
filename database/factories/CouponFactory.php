<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('???###')),
            'type' => fake()->randomElement(CouponType::cases()),
            'value' => fake()->randomFloat(2, 5, 100),
            'min_purchase_amount' => fake()->optional()->randomFloat(2, 50, 500),
            'starts_at' => null,
            'expires_at' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'usage_limit' => fake()->optional()->numberBetween(10, 100),
            'used_count' => 0,
            'active' => true,
        ];
    }

    public function fixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CouponType::FIXED,
        ]);
    }

    public function percentage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CouponType::PERCENTAGE,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
