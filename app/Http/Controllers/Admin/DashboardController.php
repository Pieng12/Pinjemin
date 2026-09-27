<?php

namespace App\Http\Controllers\Admin;

use App\BookingStatus;
use App\Http\Controllers\Controller;
use App\ItemStatus;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'totalUsers' => User::count(),
            'totalCategories' => Category::count(),
            'activeCategories' => Category::active()->count(),
            'totalItems' => Item::count(),
            'activeItems' => Item::marketplace()->count(),
            'inactiveItems' => Item::query()->where(function ($query): void {
                $query->where('is_active', false)->orWhere('status', ItemStatus::Inactive->value);
            })->count(),
            'totalBookings' => Booking::count(),
            'pendingBookings' => Booking::query()->where(function ($query): void {
                $query->whereIn('status', [BookingStatus::Pending->value, BookingStatus::PaymentReview->value])
                    ->orWhere('refund_status', 'pending');
            })->count(),
            'approvedBookings' => Booking::query()->where('status', BookingStatus::Approved->value)->count(),
            'cancelledBookings' => Booking::query()->where('status', BookingStatus::Cancelled->value)->count(),
        ]);
    }
}
