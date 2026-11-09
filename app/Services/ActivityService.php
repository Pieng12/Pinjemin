<?php

namespace App\Services;

use App\BookingStatus;
use App\Models\Booking;
use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityService
{
    public function __construct(private BookingActionPresenter $actions) {}

    /** @return array{tasks: array<int, array<string, mixed>>, bookingCount: int, identityCount: int, ownerCount: int, renterCount: int} */
    public function forUser(User $user, string $context = 'account'): array
    {
        abort_if($context === 'admin' && ! $user->isAdmin(), 403);
        $query = Booking::query()->where(function (Builder $query): void {
            $query->whereNotIn('status', [BookingStatus::Cancelled, BookingStatus::Rejected, BookingStatus::Expired, BookingStatus::Completed])
                ->orWhere('refund_status', 'pending')
                ->orWhereHas('payout', fn (Builder $payout) => $payout->whereIn('status', ['pending', 'processing', 'on_hold'])->orWhere(fn (Builder $paid) => $paid->where('status', 'paid')->whereNull('received_at')))
                ->orWhereHas('conditionSessions', fn (Builder $sessions) => $sessions->where('status', 'disputed'));
        });
        if ($context !== 'admin') {
            $query->where(function (Builder $query) use ($user, $context): void {
                if ($context !== 'owner') {
                    $query->where('renter_user_id', $user->id);
                }
                if ($context !== 'renter') {
                    $method = $context === 'owner' ? 'whereHas' : 'orWhereHas';
                    $query->{$method}('item', fn (Builder $items) => $items->where('user_id', $user->id));
                }
            });
        }
        $query->with([
            'item.user.paymentMethods', 'payments', 'payout', 'conditionSessions.reports',
            'renter.identityVerifications' => fn (HasMany $identities) => $identities->select(['id', 'user_id', 'status', 'verified_until'])->latest('id')->limit(1),
            'identityVerification:id,user_id,status,verified_until',
        ]);
        $tasks = [];
        $bookingCount = 0;
        $ownerCount = 0;
        $renterCount = 0;
        $sort = fn (array $left, array $right): int => ($left['deadline']?->timestamp ?? PHP_INT_MAX) <=> ($right['deadline']?->timestamp ?? PHP_INT_MAX) ?: $right['created'] <=> $left['created'];
        foreach ($query->lazyById(100) as $booking) {
            if ($booking->effectiveStatus() === BookingStatus::Expired) {
                continue;
            }
            $viewer = $context === 'admin' ? 'admin' : ($booking->item->user_id === $user->id ? 'owner' : 'renter');
            if ($viewer === 'admin' && $booking->status === BookingStatus::Pending) {
                continue;
            }
            $action = $this->actions->describe($booking, $user, $viewer);
            if (! $action['actionable']) {
                continue;
            }
            $bookingCount++;
            if ($viewer === 'owner') {
                $ownerCount++;
            } elseif ($viewer === 'renter') {
                $renterCount++;
            }
            $tasks[] = $action + ['context' => $booking->item->name.' · '.$booking->booking_code, 'created' => $booking->created_at->timestamp];
            usort($tasks, $sort);
            $tasks = array_slice($tasks, 0, 5);
        }
        $identityCount = 0;
        if ($context === 'admin') {
            $identities = IdentityVerification::query()->where('status', 'pending')->with('user:id,name')->latest('id');
            $identityCount = $identities->count();
            foreach ($identities->limit(5)->get() as $identity) {
                $tasks[] = ['title' => 'Periksa identitas', 'description' => 'Pengajuan menunggu pemeriksaan manual.', 'label' => 'Periksa pengajuan', 'href' => route('identity.show', $identity), 'actionable' => true, 'deadline' => null, 'context' => $identity->user->name, 'created' => $identity->created_at->timestamp];
            }
        }
        usort($tasks, $sort);

        return ['tasks' => array_slice($tasks, 0, 5), 'bookingCount' => $bookingCount, 'identityCount' => $identityCount, 'ownerCount' => $ownerCount, 'renterCount' => $renterCount];
    }
}
