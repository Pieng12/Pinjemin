<?php

namespace App\Http\Controllers\Admin;

use App\BookingPaymentStatus;
use App\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManageBookingPayoutRequest;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingPayout;
use App\Models\BookingRefund;
use App\Services\PayoutService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['tab' => ['nullable', Rule::in(['all', 'review', 'refund', 'ready', 'hold', 'paid'])]]);
        $tab = $filters['tab'] ?? 'all';
        $bookings = Booking::query()->whereHas('payout')
            ->with(['item.user', 'renter', 'payments', 'payout', 'refund', 'conditionSessions'])
            ->when($tab === 'review', fn ($query) => $query->where('status', BookingStatus::PaymentReview))
            ->when($tab === 'refund', fn ($query) => $query->where('refund_status', 'pending'))
            ->when($tab === 'ready', fn ($query) => $query->where('status', BookingStatus::Completed)->whereHas('payout', fn ($payout) => $payout->where('status', 'pending')))
            ->when($tab === 'hold', fn ($query) => $query->whereHas('payout', fn ($payout) => $payout->whereIn('status', ['on_hold', 'processing'])))
            ->when($tab === 'paid', fn ($query) => $query->whereHas('payout', fn ($payout) => $payout->where('status', 'paid')))
            ->latest('id')->paginate(15)->withQueryString();
        $received = (float) BookingPayment::query()->whereHas('booking.payout')->where('status', BookingPaymentStatus::Verified)->sum('amount');
        $refunded = (float) BookingRefund::query()->whereHas('booking.payout')->sum('amount');
        $paid = (float) BookingPayout::query()->where('status', 'paid')->sum('amount');
        $commission = (float) BookingPayout::query()->where('status', 'paid')->sum('commission_amount');

        return view('admin.finance', compact('bookings', 'tab', 'received', 'refunded', 'paid', 'commission') + [
            'held' => round($received - $refunded - $paid - $commission, 2),
        ]);
    }

    public function payout(ManageBookingPayoutRequest $request, Booking $booking, PayoutService $payouts): RedirectResponse
    {
        $path = $request->input('action') === 'paid' ? $request->file('proof')->store('payout-proofs/'.$booking->booking_code, 'local') : null;
        if ($path === false) {
            throw ValidationException::withMessages(['proof' => 'Bukti pencairan gagal disimpan.']);
        }
        try {
            $payouts->act($booking, $request->user(), $request->validated(), $path);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['transaction_reference' => 'Referensi transfer ini sudah digunakan untuk pencairan lain.']);
            }
            throw $exception;
        }

        return back()->with('status', 'Status pencairan berhasil diperbarui.');
    }

    public function refundDestination(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('setRefundDestination', $booking);
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_identifier' => ['required', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($booking, $request, $data): void {
            $booking->item()->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($request->user())->authorize('setRefundDestination', $booking);
            if ($booking->refund_destination) {
                throw ValidationException::withMessages(['refund' => 'Rekening refund sudah dikunci. Hubungi admin jika ada kesalahan sebelum transfer.']);
            }
            $booking->update(['refund_destination' => $data]);
        });

        return back()->with('status', 'Rekening refund disimpan. Admin akan menggunakan tujuan ini.');
    }

    public function claimRefund(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('recordRefund', $booking);
        abort_unless($booking->collectsViaPlatform(), 403);
        DB::transaction(function () use ($booking, $request): void {
            $booking->item()->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($request->user())->authorize('recordRefund', $booking);
            if (! $booking->refund_destination || $booking->refund_claimed_by_user_id) {
                throw ValidationException::withMessages(['refund' => 'Rekening refund belum diisi atau refund sudah ditangani admin lain.']);
            }
            $booking->update(['refund_claimed_by_user_id' => $request->user()->id, 'refund_claimed_at' => now()]);
        });

        return back()->with('status', 'Refund ditugaskan kepadamu. Periksa tujuan dan nominal sebelum mentransfer.');
    }

    public function document(Booking $booking): Response
    {
        Gate::authorize('viewPayout', $booking);
        $booking->load(['payout', 'item.user']);
        abort_unless($booking->payout?->status === 'paid', 404);

        return Pdf::loadView('bookings.payout-receipt', ['booking' => $booking, 'payout' => $booking->payout])
            ->setPaper('a4')->download('OUT-'.$booking->booking_code.'.pdf');
    }

    public function ownerIndex(Request $request): View
    {
        $query = BookingPayout::query()->whereHas('booking.item', fn ($query) => $query->where('user_id', $request->user()->id));

        return view('owner.payouts', [
            'payouts' => (clone $query)->with(['booking.item', 'booking.payments', 'booking.conditionSessions'])->latest('id')->paginate(12),
            'paidAmount' => (clone $query)->where('status', 'paid')->sum('amount'),
            'pendingAmount' => (clone $query)->whereNotIn('status', ['paid', 'cancelled'])->sum('amount'),
        ]);
    }
}
