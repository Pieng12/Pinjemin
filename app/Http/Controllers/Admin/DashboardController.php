<?php

namespace App\Http\Controllers\Admin;

use App\BookingStatus;
use App\Http\Controllers\Controller;
use App\ItemStatus;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ActivityService $activities): View
    {
        $activity = $activities->forUser($request->user(), 'admin');

        return view('admin.dashboard', [
            'activity' => $activity,
            'totalUsers' => User::count(),
            'totalCategories' => Category::count(),
            'activeCategories' => Category::active()->count(),
            'totalItems' => Item::count(),
            'activeItems' => Item::marketplace()->count(),
            'inactiveItems' => Item::query()->where(function ($query): void {
                $query->where('is_active', false)->orWhere('status', ItemStatus::Inactive->value);
            })->count(),
            'totalBookings' => Booking::count(),
            'pendingBookings' => $activity['bookingCount'],
            'approvedBookings' => Booking::query()->where('status', BookingStatus::Approved->value)->count(),
            'cancelledBookings' => Booking::query()->where('status', BookingStatus::Cancelled->value)->count(),
        ]);
    }
}
