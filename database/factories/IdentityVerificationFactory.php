<?php

namespace Database\Factories;

use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdentityVerification>
 */
class IdentityVerificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'document_type' => 'ktp',
            'legal_name' => fake()->name(), 'document_number' => '1234567890123456',
            'status' => 'pending', 'consented_at' => now(), 'retain_until' => now()->addDays(30),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => ['status' => 'verified', 'reviewed_at' => now(), 'verified_until' => now()->addYear(), 'retain_until' => now()->addYear()]);
    }
}
