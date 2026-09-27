<?php

namespace App\Http\Controllers;

use App\BookingStatus;
use App\ItemStatus;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('owner.dashboard', [
            'activeItemsCount' => $user->items()->marketplace()->count(),
            'pendingBookingsCount' => Booking::query()
                ->where(function ($query): void {
                    $query->whereIn('status', [BookingStatus::Pending->value, BookingStatus::PaymentReview->value])
                        ->orWhere('refund_status', 'pending');
                })
                ->whereHas('item', fn ($query) => $query->where('user_id', $user->id))
                ->count(),
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
