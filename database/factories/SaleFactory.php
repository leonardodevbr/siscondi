<?php

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalAmount = fake()->randomFloat(2, 10, 1000);
        $discountAmount = fake()->randomFloat(2, 0, $totalAmount * 0.2);
        $finalAmount = $totalAmount - $discountAmount;

        return [
            'user_id' => User::factory(),
            'branch_id' => Branch::where('is_main', true)->value('id') ?? Branch::factory(),
            'customer_id' => null,
            'total_amount' => $totalAmount,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'status' => SaleStatus::COMPLETED,
            'note' => fake()->optional()->sentence(),
        ];
    }
}
