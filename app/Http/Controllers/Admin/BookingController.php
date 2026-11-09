<?php

namespace App\Http\Controllers\Admin;

use App\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingActionPresenter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        if (is_string($request->query('q'))) {
            $request->merge(['q' => trim($request->query('q'))]);
        }

        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(BookingStatus::values())],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $bookings = Booking::query()
            ->with(['item.primaryPhoto', 'item.user', 'renter', 'payments'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->withStatus($status))
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters): void {
                $search = $filters['q'];
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('booking_code', 'like', '%'.$search.'%')
                        ->orWhereHas('item', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('item.user', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('renter', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.bookings.index', [
            'bookings' => $bookings,
            'totalCount' => Booking::query()->count(),
            'statuses' => BookingStatus::options(),
            'filters' => $filters,
        ]);
    }

    public function show(Booking $booking): View
    {
        $booking->load(['item.category', 'item.primaryPhoto', 'item.user', 'renter', 'payments.reviewer', 'refund', 'payout', 'conditionSessions.reports.user']);

        return view('admin.bookings.show', [
            'handoverBlocker' => app(BookingActionPresenter::class)->handoverBlocker($booking),
            'nextAction' => app(BookingActionPresenter::class)->describe($booking, request()->user(), 'admin'),
            'booking' => $booking,
        ]);
    }
}
