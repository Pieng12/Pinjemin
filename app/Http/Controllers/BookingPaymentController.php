<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingPaymentRequest;
use App\Models\Booking;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class BookingPaymentController extends Controller
{
    public function store(StoreBookingPaymentRequest $request, Booking $booking, PaymentService $payments): RedirectResponse
    {
        $path = $request->file('proof')->store('payment-proofs/'.$booking->booking_code, 'local');
        if ($path === false) {
            throw ValidationException::withMessages(['proof' => 'Bukti pembayaran gagal disimpan. Coba lagi.']);
        }
        try {
            $payments->submit($booking, $request->user(), $path, $request->validated());
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('status', $booking->collectsViaPlatform() ? 'Bukti pembayaran dikirim dan menunggu pemeriksaan admin Pinjemin.' : 'Bukti pembayaran dikirim dan menunggu pemeriksaan pemilik.');
    }
}
