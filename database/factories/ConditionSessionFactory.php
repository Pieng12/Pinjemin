<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\ConditionSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConditionSession>
 */
class ConditionSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(), 'stage' => 'handover', 'attempt' => 1, 'status' => 'waiting', 'events' => [],
        ];
    }
}
