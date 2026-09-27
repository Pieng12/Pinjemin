<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BookingInvoiceController extends Controller
{
    public function __invoke(Booking $booking): Response
    {
        Gate::authorize('downloadInvoice', $booking);

        return Pdf::loadView('bookings.invoice', ['booking' => $booking, 'invoice' => $booking->invoice_snapshot])
            ->setPaper('a4')->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false])
            ->download('INV-'.$booking->booking_code.'.pdf')->header('Cache-Control', 'private, no-store');
    }
}
