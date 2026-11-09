<?php

namespace App\Http\Controllers;

use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ActivityService $activities): View
    {
        $user = $request->user();
        $activity = $activities->forUser($user);

        return view('dashboard', [
            'activity' => $activity,
            'user' => $user,
            'rentalBookingsCount' => $user->rentalBookings()->count(),
            'totalItemsCount' => $user->items()->count(),
            'activeItemsCount' => $user->items()->marketplace()->count(),
            'pendingIncomingCount' => $activity['ownerCount'],
        ]);
    }
}
