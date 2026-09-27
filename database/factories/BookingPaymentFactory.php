<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingPayment>
 */
class BookingPaymentFactory extends Factory
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
            'submitted_by_user_id' => User::factory(),
            'kind' => 'full',
            'channel' => 'transfer',
            'amount' => 100000,
            'method_snapshot' => ['type' => 'bank', 'label' => 'BCA'],
            'proof_path' => 'payment-proofs/test.jpg',
            'transferred_at' => now(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ];
    }
}
