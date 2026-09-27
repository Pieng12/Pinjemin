<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewBookingPaymentRequest;
use App\Http\Requests\StoreBookingRefundRequest;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

class IncomingPaymentController extends Controller
{
    public function review(ReviewBookingPaymentRequest $request, Booking $booking, BookingPayment $payment, PaymentService $payments): RedirectResponse
    {
        abort_unless($payment->booking_id === $booking->id, 404);
        $payments->review($booking, $payment, $request->user(), $request->string('decision')->toString(), $request->input('owner_comment'));

        return back()->with('status', $request->input('decision') === 'verify' ? 'Pembayaran berhasil diverifikasi.' : 'Bukti ditolak dan komentar dikirim kepada penyewa.');
    }

    public function cashAndHandOver(Booking $booking, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('cashAndHandOver', $booking);
        $payments->cashAndHandOver($booking, request()->user());

        return back()->with('status', 'Pelunasan tunai dan penyerahan barang berhasil dicatat.');
    }

    public function refund(StoreBookingRefundRequest $request, Booking $booking, PaymentService $payments): RedirectResponse
    {
        $path = $request->file('proof')->store('refund-proofs/'.$booking->booking_code, 'local');
        try {
            $payments->recordRefund($booking, $request->user(), $path, $request->validated());
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('status', 'Refund seluruh pembayaran terverifikasi berhasil dicatat.');
    }
}
