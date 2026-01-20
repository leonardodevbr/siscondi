<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'cpf_cnpj' => fake()->unique()->numerify('###.###.###-##'),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'birth_date' => fake()->boolean(80)
                ? fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d')
                : null
        ];
    }
}

