<?php

namespace App\Services;

use App\BookingStatus;
use App\Models\Booking;
use App\Models\Item;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class AvailabilityService
{
    public function getAvailableQuantity(Item $item, DateTimeInterface|string $startDate, DateTimeInterface|string $endDate, ?Booking $excluding = null): int
    {
        $start = Carbon::parse($startDate)->toDateString();
        $end = Carbon::parse($endDate)->toDateString();
        $today = now(Booking::RentalTimezone)->toDateString();
        $reservations = $this->reservations([$item->id], $start, $end, $today, $excluding);

        return $this->availableThroughout($item, $this->reservationEvents($reservations, $start, $end, $today));
    }

    /** @param Builder<Item> $items */
    public function filterAvailableItems(Builder $items, string $start, string $end, int $quantity): void
    {
        $today = now(Booking::RentalTimezone)->toDateString();
        $ids = [];
        (clone $items)->where('quantity', '>=', $quantity)->select(['id', 'quantity'])
            ->chunkById(150, function (Collection $batch) use ($start, $end, $today, $quantity, &$ids): void {
                $byItem = $this->reservations($batch->modelKeys(), $start, $end, $today)->groupBy('item_id');
                foreach ($batch as $item) {
                    $events = $this->reservationEvents($byItem->get($item->id, []), $start, $end, $today);
                    if ($this->availableThroughout($item, $events) >= $quantity) {
                        $ids[] = $item->id;
                    }
                }
            });

        $items->whereIntegerInRaw('id', $ids);
    }

    /** @return array<int, array{date: string, available_quantity: int}> */
    public function calendar(Item $item, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $today = now(Booking::RentalTimezone)->toDateString();
        $reservations = $this->reservations([$item->id], $start->toDateString(), $end->toDateString(), $today);
        $events = $this->reservationEvents($reservations, $start->toDateString(), $end->toDateString(), $today);
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

    /** @param array<string, int> $events */
    private function availableThroughout(Item $item, array $events): int
    {
        $reserved = 0;
        $peak = 0;
        foreach ($events as $change) {
            $reserved += $change;
            $peak = max($peak, $reserved);
        }

        return max(0, $item->quantity - $peak);
    }

    /**
     * @param  list<int>  $itemIds
     * @return Collection<int, Booking>
     */
    private function reservations(array $itemIds, string $start, string $end, string $today, ?Booking $excluding = null): Collection
    {
        $afterEnd = Carbon::parse($end)->addDay()->toDateString();

        return Booking::query()->whereIntegerInRaw('item_id', $itemIds)->reserving()->where('start_date', '<', $afterEnd)
            ->where(function ($query) use ($start, $today): void {
                $query->where('end_date', '>=', $start)->orWhere(function ($query) use ($today): void {
                    $query->where('status', BookingStatus::Approved->value)
                        ->whereNotNull('handed_over_at')
                        ->where('end_date', '<', $today);
                });
            })
            ->when($excluding, fn ($query) => $query->whereKeyNot($excluding->getKey()))
            ->get(['item_id', 'start_date', 'end_date', 'quantity', 'status', 'handed_over_at']);
    }

    /**
     * @param  iterable<Booking>  $reservations
     * @return array<string, int>
     */
    private function reservationEvents(iterable $reservations, string $start, string $end, string $today): array
    {
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
