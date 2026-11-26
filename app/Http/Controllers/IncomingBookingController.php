<?php

namespace App\Http\Controllers;

use App\BookingStatus;
use App\Models\Booking;
use App\Services\AvailabilityService;
use App\Services\BookingActionPresenter;
use App\Services\BookingService;
use App\Services\ItemChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncomingBookingController extends Controller
{
    public function index(Request $request): View
    {
        if (is_string($request->query('q'))) {
            $request->merge(['q' => trim($request->query('q'))]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(BookingStatus::values())],
        ]);

        $bookings = Booking::query()
            ->whereHas('item', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['item.primaryPhoto', 'renter', 'payments'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->withStatus($status))
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query->where('booking_code', 'like', '%'.$filters['q'].'%')
                        ->orWhereHas('item', fn ($query) => $query->where('name', 'like', '%'.$filters['q'].'%'))
                        ->orWhereHas('renter', fn ($query) => $query->where('name', 'like', '%'.$filters['q'].'%'));
                });
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('incoming-bookings.index', [
            'bookings' => $bookings,
            'totalCount' => Booking::query()->whereHas('item', fn ($query) => $query->where('user_id', $request->user()->id))->count(),
            'statuses' => BookingStatus::options(),
            'filters' => $filters,
        ]);
    }

    public function show(Booking $booking, AvailabilityService $availability, ItemChatService $chat): View
    {
        Gate::authorize('viewAsOwner', $booking);

        $booking->load(['item.category', 'item.primaryPhoto', 'item.user', 'renter', 'payments.reviewer', 'refund', 'payout', 'conditionSessions.reports.user']);

        return view('bookings.show', [
            'handoverBlocker' => app(BookingActionPresenter::class)->handoverBlocker($booking),
            'nextAction' => app(BookingActionPresenter::class)->describe($booking, request()->user(), 'owner'),
            'approvalBlocker' => $booking->status === BookingStatus::Pending ? app(BookingActionPresenter::class)->approvalBlocker($booking) : null,
            'booking' => $booking,
            'viewer' => 'owner',
            'chatAvailable' => $chat->bookingChatAvailable($booking),
            'hasActivePaymentMethod' => $booking->item->user->paymentMethods()
                ->where('is_active', true)
                ->when($booking->collectsViaPlatform(), fn ($query) => $query->where('type', '!=', 'qris')->whereNotNull('account_identifier'))
                ->exists(),
            'stockConflict' => in_array($booking->status, [BookingStatus::Approved, BookingStatus::PartiallyPaid], true)
                && $availability->getAvailableQuantity($booking->item,
                    max(now(Booking::RentalTimezone)->toDateString(), $booking->start_date->toDateString()),
                    max(now(Booking::RentalTimezone)->toDateString(), $booking->end_date->toDateString()), $booking) < $booking->quantity,
        ]);
    }

    public function approve(Booking $booking, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('approve', $booking);

        $bookings->approve($booking);

        return redirect()->route('incoming-bookings.show', $booking)->with('status', 'Pemesanan diterima. Penyewa dapat melanjutkan pembayaran.');
    }

    public function reject(Request $request, Booking $booking, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('reject', $booking);

        $validated = $request->validate([
            'owner_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $bookings->reject($booking, $validated['owner_note'] ?? null);

        return redirect()->route('incoming-bookings.show', $booking)->with('status', 'Permintaan sewa berhasil ditolak.');
    }

    public function quoteDelivery(Request $request, Booking $booking, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('quoteDelivery', $booking);
        $data = $request->validate(['delivery_fee' => ['required', 'numeric', 'min:0', 'max:999999999.99', 'decimal:0,2']]);
        $bookings->quoteDelivery($booking, (float) $data['delivery_fee']);

        return redirect()->route('incoming-bookings.show', $booking)->with('status', 'Tawaran biaya pengiriman dikirim. Stok ditahan sampai batas konfirmasi.');
    }

    public function cancel(Request $request, Booking $booking, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('cancelAsOwner', $booking);
        $data = $request->validate(['cancellation_reason' => ['required', 'string', 'max:1000']]);
        $bookings->cancel($booking, $request->user(), $data['cancellation_reason']);

        return redirect()->route('incoming-bookings.show', $booking)->with('status', 'Pemesanan dibatalkan dan reservasi stok dibebaskan.');
    }

    public function handOver(Booking $booking, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('handOver', $booking);
        $bookings->handOver($booking);

        return redirect()->route('incoming-bookings.show', $booking)->with('status', 'Penyerahan barang dicatat.');
    }

    public function complete(Booking $booking, BookingService $bookings): RedirectResponse
    {
        Gate::authorize('complete', $booking);
        $bookings->complete($booking);

        return redirect()->route('incoming-bookings.show', $booking)->with('status', 'Barang sudah kembali. Penyewaan selesai.');
    }
}
