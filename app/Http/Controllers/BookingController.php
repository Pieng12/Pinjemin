<?php

namespace App\Http\Controllers;

use App\BookingStatus;
use App\Http\Requests\StoreBookingRequest;
use App\ItemStatus;
use App\Models\Booking;
use App\Models\Item;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\RentalPriceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function availability(Request $request, Item $item, AvailabilityService $availability, RentalPriceCalculator $calculator): JsonResponse
    {
        abort_unless($item->is_active && $item->status === ItemStatus::Available, 404);

        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now(Booking::RentalTimezone)->toDateString()],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $pricing = $calculator->calculate($item, $validated['start_date'], $validated['end_date'], 1);

        return response()->json([
            'available_quantity' => $availability->getAvailableQuantity($item, $validated['start_date'], $validated['end_date']),
            'item_quantity' => $item->quantity,
            'rental_days' => $pricing['rental_days'],
            'daily_price' => (float) $item->daily_price,
            'deposit_per_unit' => (float) ($item->deposit_amount ?? 0),
        ])->header('Cache-Control', 'no-store');
    }

    public function calendar(Request $request, Item $item, AvailabilityService $availability): JsonResponse
    {
        abort_unless($item->is_active && $item->status === ItemStatus::Available, 404);
        $validated = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $month = Carbon::createFromFormat('!Y-m', $validated['month'], Booking::RentalTimezone);

        return response()->json([
            'month' => $validated['month'], 'today' => now(Booking::RentalTimezone)->toDateString(),
            'item_quantity' => $item->quantity, 'days' => $availability->calendar($item, $month),
        ])->header('Cache-Control', 'no-store');
    }

    public function store(StoreBookingRequest $request, Item $item, BookingService $bookings): RedirectResponse
    {
        $booking = $bookings->create($request->user(), $item, $request->validated());

        return redirect()->route('my-bookings.show', $booking)->with('status', 'Booking berhasil diajukan.');
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(BookingStatus::values())],
        ]);

        $bookings = $request->user()
            ->rentalBookings()
            ->with(['item.primaryPhoto', 'item.user', 'payments'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->withStatus($status))
            ->latest()
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('bookings.index', [
            'bookings' => $bookings,
            'statuses' => BookingStatus::options(),
            'filters' => $filters,
        ]);
    }

    public function show(Booking $booking): View
    {
        Gate::authorize('viewAsRenter', $booking);

        $booking->load(['item.category', 'item.primaryPhoto', 'item.user', 'renter', 'payments.reviewer', 'refund']);

        return view('bookings.show', [
            'booking' => $booking,
            'viewer' => 'renter',
        ]);
    }

    public function cancel(Booking $booking, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('cancel', $booking);

        $bookings->cancel($booking, request()->user());

        return redirect()->route('my-bookings.show', $booking)->with('status', 'Booking berhasil dibatalkan.');
    }

    public function confirmDelivery(Booking $booking, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('confirmDelivery', $booking);
        $bookings->confirmDelivery($booking);

        return redirect()->route('my-bookings.show', $booking)->with('status', 'Total biaya disetujui. Booking sudah dikonfirmasi.');
    }

    public function declineDelivery(Booking $booking, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('confirmDelivery', $booking);
        $bookings->cancel($booking, request()->user(), 'Penyewa menolak tawaran ongkir.');

        return redirect()->route('my-bookings.show', $booking)->with('status', 'Tawaran ditolak dan booking dibatalkan.');
    }
}
