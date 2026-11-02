<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingPayout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingPayout>
 */
class BookingPayoutFactory extends Factory
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
            'status' => 'pending',
            'commission_rate' => 5,
            'commission_amount' => 5000,
            'amount' => 95000,
            'destination_snapshot' => ['type' => 'bank', 'label' => 'Bank Uji', 'account_name' => 'Pemilik', 'account_identifier' => '1234567890'],
            'events' => [],
        ];
    }
}
