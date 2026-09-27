<?php

namespace App\Services;

use App\BookingStatus;
use App\Models\Booking;
use App\Models\Item;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class AvailabilityService
{
    public function getAvailableQuantity(Item $item, DateTimeInterface|string $startDate, DateTimeInterface|string $endDate, ?Booking $excluding = null): int
    {
        $reserved = 0;
        $peak = 0;
        foreach ($this->reservationEvents($item, Carbon::parse($startDate)->toDateString(), Carbon::parse($endDate)->toDateString(), $excluding) as $change) {
            $reserved += $change;
            $peak = max($peak, $reserved);
        }

        return max(0, $item->quantity - $peak);
    }

    /** @return array<int, array{date: string, available_quantity: int}> */
    public function calendar(Item $item, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $events = $this->reservationEvents($item, $start->toDateString(), $end->toDateString());
        $reserved = 0;
        $days = [];
        for ($day = $start; $day->lte($end); $day->addDay()) {
            $date = $day->toDateString();
            $reserved += $events[$date] ?? 0;
            $days[] = ['date' => $date, 'available_quantity' => max(0, $item->quantity - $reserved)];
        }

        return $days;
    }

    public function minimumStockRequired(Item $item): int
    {
        $today = now(Booking::RentalTimezone)->toDateString();
        $end = $item->bookings()->reserving()->max('end_date') ?? $today;

        return $item->quantity - $this->getAvailableQuantity($item, $today, max($today, $end));
    }

    /** @return array<string, int> */
    private function reservationEvents(Item $item, string $start, string $end, ?Booking $excluding = null): array
    {
        $today = now(Booking::RentalTimezone)->toDateString();
        $reservations = $item->bookings()->reserving()->where('start_date', '<=', $end)
            ->where(function ($query) use ($start, $today): void {
                $query->where('end_date', '>=', $start)->orWhere(function ($query) use ($today): void {
                    $query->where('status', BookingStatus::Approved->value)
                        ->whereNotNull('handed_over_at')
                        ->where('end_date', '<', $today);
                });
            })
            ->when($excluding, fn ($query) => $query->whereKeyNot($excluding->getKey()))
            ->get(['start_date', 'end_date', 'quantity', 'status', 'handed_over_at']);
        $events = [];
        foreach ($reservations as $booking) {
            $from = max($start, $booking->start_date->toDateString());
            $until = $booking->status === BookingStatus::Approved
                && $booking->handed_over_at
                && $booking->end_date->toDateString() < $today
                ? $end : min($end, $booking->end_date->toDateString());
            if ($from > $until) {
                continue;
            }
            $events[$from] = ($events[$from] ?? 0) + $booking->quantity;
            if ($until < $end) {
                $after = Carbon::parse($until)->addDay()->toDateString();
                $events[$after] = ($events[$after] ?? 0) - $booking->quantity;
            }
        }
        ksort($events);

        return $events;
    }
}
