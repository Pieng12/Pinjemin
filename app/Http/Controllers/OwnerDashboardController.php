<?php

namespace App\Http\Controllers;

use App\ItemStatus;
use App\Models\Booking;
use App\Services\ActivityService;
use App\Services\OwnerBusinessReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerDashboardController extends Controller
{
    public function __invoke(Request $request, ActivityService $activities, OwnerBusinessReport $reports): View
    {
        $user = $request->user();
        $activity = $activities->forUser($user, 'owner');
        $days = match ($request->query('period')) {
            '7' => 7,
            '90' => 90,
            default => 30,
        };

        return view('owner.dashboard', [
            'activity' => $activity,
            'businessReport' => $reports->forOwner($user, $days),
            'activeItemsCount' => $user->items()->marketplace()->count(),
            'pendingBookingsCount' => $activity['bookingCount'],
            'totalItemsCount' => $user->items()->count(),
            'inactiveItemsCount' => $user->items()
                ->where(function ($query): void {
                    $query->where('is_active', false)
                        ->orWhere('status', ItemStatus::Inactive->value);
                })
                ->count(),
            'recentIncomingBookings' => Booking::query()
                ->whereHas('item', fn ($query) => $query->where('user_id', $user->id))
                ->with(['item.primaryPhoto', 'renter', 'payments'])
                ->latest()
                ->orderByDesc('id')
                ->limit(4)
                ->get(),
            'recentItems' => $user->items()
                ->with(['category', 'primaryPhoto'])
                ->latest()
                ->orderByDesc('id')
                ->limit(4)
                ->get(),
        ]);
    }
}
