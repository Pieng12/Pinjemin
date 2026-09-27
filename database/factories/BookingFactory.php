<?php

namespace Database\Factories;

use App\BookingStatus;
use App\Models\Booking;
use App\Models\Item;
use App\Models\User;
use App\Services\RentalPriceCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $item = Item::factory();
        $startDate = fake()->dateTimeBetween('+1 day', '+15 days');
        $endDate = (clone $startDate)->modify('+'.fake()->numberBetween(0, 4).' days');
        $quantity = fake()->numberBetween(1, 2);
        $dailyPrice = fake()->numberBetween(50000, 250000);
        $depositAmount = fake()->numberBetween(0, 300000);
        $pricing = (new RentalPriceCalculator)->calculateFromValues(
            dailyPrice: $dailyPrice,
            depositPerUnit: $depositAmount,
            startDate: $startDate,
            endDate: $endDate,
            quantity: $quantity,
        );

        return [
            'booking_code' => 'PNJ-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'item_id' => $item,
            'renter_user_id' => User::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'quantity' => $quantity,
            'daily_price' => $dailyPrice,
            'rental_days' => $pricing['rental_days'],
            'subtotal' => $pricing['subtotal'],
            'deposit_amount' => $pricing['deposit_total'],
            'total_amount' => $pricing['total_amount'],
            'status' => BookingStatus::Pending->value,
            'renter_note' => fake()->optional()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Approved->value,
            'approved_at' => now(),
            'accepted_at' => now(),
            'payment_verified_at' => now(),
            'payment_legacy' => true,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Rejected->value,
            'rejected_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);
    }
}
