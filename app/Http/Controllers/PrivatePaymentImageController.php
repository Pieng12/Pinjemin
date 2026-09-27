<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\UserPaymentMethod;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivatePaymentImageController extends Controller
{
    public function method(UserPaymentMethod $paymentMethod): StreamedResponse
    {
        Gate::authorize('view', $paymentMethod);

        return $this->image($paymentMethod->qris_path);
    }

    public function bookingMethod(Booking $booking, int $index): StreamedResponse
    {
        Gate::authorize('viewPaymentFiles', $booking);
        $method = array_values($booking->payment_methods_snapshot ?? [])[$index] ?? null;
        abort_unless($method && ($method['qris_path'] ?? null), 404);

        return $this->image($method['qris_path']);
    }

    public function proof(Booking $booking, BookingPayment $payment): StreamedResponse
    {
        Gate::authorize('viewPaymentFiles', $booking);
        abort_unless($payment->booking_id === $booking->id, 404);

        return $this->image($payment->proof_path);
    }

    public function refund(Booking $booking): StreamedResponse
    {
        Gate::authorize('viewPaymentFiles', $booking);
        $refund = $booking->refund;
        abort_unless($refund, 404);

        return $this->image($refund->proof_path);
    }

    private function image(?string $path): StreamedResponse
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
