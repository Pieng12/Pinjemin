<?php

namespace App\Http\Controllers;

use App\BookingStatus;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'user' => $user,
            'rentalBookingsCount' => $user->rentalBookings()->count(),
            'totalItemsCount' => $user->items()->count(),
            'activeItemsCount' => $user->items()->marketplace()->count(),
            'pendingIncomingCount' => Booking::query()
                ->where(function ($query): void {
                    $query->whereIn('status', [BookingStatus::Pending->value, BookingStatus::PaymentReview->value])
                        ->orWhere('refund_status', 'pending');
                })
                ->whereHas('item', fn ($query) => $query->where('user_id', $user->id))
                ->count(),
        ]);
    }
}
