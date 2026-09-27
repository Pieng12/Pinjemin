<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingRefund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingRefund>
 */
class BookingRefundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'recorded_by_user_id' => User::factory(),
            'amount' => 100000,
            'proof_path' => 'refund-proofs/test.jpg',
            'refunded_at' => now(),
        ];
    }
}
