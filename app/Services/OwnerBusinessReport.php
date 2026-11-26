<?php

namespace App\Services;

use App\BookingPaymentStatus;
use App\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPayout;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OwnerBusinessReport
{
    /**
     * @return array{
     *     days: int, from: CarbonImmutable, through: CarbonImmutable,
     *     periods: list<array{from: CarbonImmutable, through: CarbonImmutable, orders: int, transferredCents: int}>,
     *     orders: int, renters: int, completed: int, transferredCents: int, commissionCents: int,
     *     statuses: array<string, int>, popularItems: list<array{slug: string, name: string, orders: int, completed: int}>
     * }
     */
    public function forOwner(User $owner, int $days): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $interval = $days === 7 ? 1 : intdiv($days, 6);
        $through = CarbonImmutable::now(Booking::RentalTimezone)->startOfDay();
        $from = $through->subDays($days - 1);
        $startUtc = $from->utc();
        $endUtc = $through->addDay()->utc();

        $periods = [];
        for ($offset = 0; $offset < $days; $offset += $interval) {
            $periods[] = [
                'from' => $from->addDays($offset),
                'through' => $from->addDays($offset + $interval - 1),
                'orders' => 0,
                'transferredCents' => 0,
            ];
        }

        $orders = Booking::query()->whereHas('item', fn (Builder $items) => $items->whereBelongsTo($owner, 'user'))
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endUtc);
        $renters = (clone $orders)->distinct()->count('renter_user_id');
        $statuses = array_fill_keys(['pending', 'in_progress', 'approved', 'completed', 'rejected', 'cancelled', 'expired'], 0);
        $byItem = [];

        $orders->select(['id', 'item_id', 'status', 'created_at', 'quote_expires_at', 'payment_due_at'])
            ->chunkById(250, function (Collection $bookings) use ($from, $interval, &$statuses, &$periods, &$byItem): void {
                $bookings->filter(fn (Booking $booking) => $booking->status === BookingStatus::AwaitingPayment)
                    ->load(['payments' => fn ($payments) => $payments->select(['booking_id', 'status', 'amount'])
                        ->where('status', BookingPaymentStatus::Verified)]);

                foreach ($bookings as $booking) {
                    $index = $this->periodIndex($from, $booking->created_at->toImmutable(), $interval);
                    $periods[$index]['orders']++;
                    $status = $booking->effectiveStatus();
                    $group = array_key_exists($status->value, $statuses) ? $status->value : 'in_progress';
                    $statuses[$group]++;
                    $itemId = $booking->item_id;
                    $byItem[$itemId] ??= ['orders' => 0, 'completed' => 0];
                    $byItem[$itemId]['orders']++;
                    $byItem[$itemId]['completed'] += (int) ($status === BookingStatus::Completed);
                }
            });

        $topItems = collect($byItem)->sortByDesc('orders')->take(5);
        $items = $owner->items()->whereKey($topItems->keys()->all())->get(['id', 'name', 'slug'])->keyBy('id');
        $popularItems = [];
        foreach ($topItems as $itemId => $counts) {
            $item = $items->get($itemId);
            if ($item) {
                $popularItems[] = ['slug' => $item->slug, 'name' => $item->name, ...$counts];
            }
        }

        $commissionCents = 0;
        $transfers = BookingPayout::query()
            ->whereHas('booking.item', fn (Builder $items) => $items->whereBelongsTo($owner, 'user'))
            ->where('status', 'paid')
            ->where('paid_at', '>=', $startUtc)
            ->where('paid_at', '<', $endUtc)
            ->select(['id', 'paid_at', 'amount', 'commission_amount'])
            ->lazyById(250);

        foreach ($transfers as $transfer) {
            $index = $this->periodIndex($from, $transfer->paid_at->toImmutable(), $interval);
            $periods[$index]['transferredCents'] += (int) round((float) $transfer->amount * 100);
            $commissionCents += (int) round((float) $transfer->commission_amount * 100);
        }

        return [
            'days' => $days,
            'from' => $from,
            'through' => $through,
            'periods' => $periods,
            'orders' => array_sum($statuses),
            'renters' => $renters,
            'completed' => $statuses['completed'],
            'transferredCents' => array_sum(array_column($periods, 'transferredCents')),
            'commissionCents' => $commissionCents,
            'statuses' => $statuses,
            'popularItems' => $popularItems,
        ];
    }

    private function periodIndex(CarbonImmutable $from, CarbonImmutable $event, int $interval): int
    {
        $day = $event->setTimezone(Booking::RentalTimezone)->startOfDay();

        return intdiv((int) $from->diffInDays($day), $interval);
    }
}
