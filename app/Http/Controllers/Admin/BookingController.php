<?php

namespace App\Http\Controllers\Admin;

use App\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(BookingStatus::values())],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $bookings = Booking::query()
            ->with(['item.primaryPhoto', 'item.user', 'renter', 'payments'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->withStatus($status))
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('booking_code', 'like', '%'.$search.'%')
                        ->orWhereHas('item', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('renter', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.bookings.index', [
            'bookings' => $bookings,
            'statuses' => BookingStatus::options(),
            'filters' => $filters,
        ]);
    }

    public function show(Booking $booking): View
    {
        $booking->load(['item.category', 'item.primaryPhoto', 'item.user', 'renter', 'payments.reviewer', 'refund']);

        return view('admin.bookings.show', [
            'booking' => $booking,
        ]);
    }
}
