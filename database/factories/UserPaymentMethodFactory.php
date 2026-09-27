<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPaymentMethod>
 */
class UserPaymentMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'bank',
            'provider' => 'BCA',
            'account_name' => fake()->name(),
            'account_identifier' => fake()->numerify('##########'),
            'is_active' => true,
            'is_primary' => false,
            'sort_order' => 1,
        ];
    }
}
