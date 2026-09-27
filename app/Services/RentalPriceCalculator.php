<?php

namespace App\Services;

use App\Models\Item;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class RentalPriceCalculator
{
    /** @return array{rental_days: int, subtotal: float, deposit_total: float, total_amount: float} */
    public function calculate(Item $item, DateTimeInterface|string $startDate, DateTimeInterface|string $endDate, int $quantity): array
    {
        return $this->calculateFromValues(
            dailyPrice: (float) $item->daily_price,
            depositPerUnit: (float) ($item->deposit_amount ?? 0),
            startDate: $startDate,
            endDate: $endDate,
            quantity: $quantity,
        );
    }

    /** @return array{rental_days: int, subtotal: float, deposit_total: float, total_amount: float} */
    public function calculateFromValues(float|int|string $dailyPrice, float|int|string $depositPerUnit, DateTimeInterface|string $startDate, DateTimeInterface|string $endDate, int $quantity): array
    {
        $rentalDays = $this->rentalDays($startDate, $endDate);
        $subtotal = round((float) $dailyPrice * $rentalDays * $quantity, 2);
        $depositTotal = round((float) $depositPerUnit * $quantity, 2);

        return [
            'rental_days' => $rentalDays,
            'subtotal' => $subtotal,
            'deposit_total' => $depositTotal,
            'total_amount' => round($subtotal + $depositTotal, 2),
        ];
    }

    public function rentalDays(DateTimeInterface|string $startDate, DateTimeInterface|string $endDate): int
    {
        $start = rescue(fn () => Carbon::parse($startDate)->startOfDay(), report: false);
        $end = rescue(fn () => Carbon::parse($endDate)->startOfDay(), report: false);

        if (! $start || ! $end || $end->lt($start)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }
}
