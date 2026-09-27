<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BookingPaymentReceiptController extends Controller
{
    public function __invoke(Booking $booking): Response
    {
        Gate::authorize('downloadPaymentReceipt', $booking);

        return Pdf::loadView('bookings.payment-receipt', ['booking' => $booking, 'receipt' => $booking->payment_receipt_snapshot])
            ->setPaper('a4')->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false])
            ->download('PAY-'.$booking->booking_code.'.pdf')->header('Cache-Control', 'private, no-store');
    }
}
