<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>PAY-{{ $booking->booking_code }}</title>
    <style>
        @page { margin: 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #27272a; line-height: 1.6; }
        h1 { margin: 0; color: #1d4ed8; font-size: 26px; } h2 { margin: 0 0 8px; font-size: 13px; }
        .header { margin-bottom: 24px; padding-bottom: 18px; border-bottom: 2px solid #2563eb; }
        .muted { color: #71717a; } .cancelled { margin-bottom: 16px; padding: 10px; background: #fef2f2; color: #b91c1c; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; } td, th { padding: 9px 0; border-bottom: 1px solid #e4e4e7; text-align: left; vertical-align: top; }
        .amount { width: 28%; text-align: right; } .total { color: #1d4ed8; font-size: 14px; font-weight: bold; }
        .note { margin-top: 28px; padding-top: 14px; border-top: 1px solid #e4e4e7; color: #71717a; font-size: 10px; }
    </style>
</head>
<body>
    @php($invoice = $receipt['invoice'])
    @php($details = $invoice['booking'])
    @php($money = fn ($amount) => 'Rp '.number_format((float) $amount, (float) $amount === floor((float) $amount) ? 0 : 2, ',', '.'))
    <div class="header"><h1>Pinjemin.</h1><h2>Konfirmasi Pembayaran Manual</h2><p>PAY-{{ $details['booking_code'] }}</p><p class="muted">Lunas diverifikasi {{ \Illuminate\Support\Carbon::parse($receipt['verified_at'])->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB</p></div>
    @if($booking->status === \App\BookingStatus::Cancelled)<div class="cancelled">BOOKING DIBATALKAN / REFUND {{ $booking->refund_status === 'refunded' ? 'SUDAH DICATAT' : 'MENUNGGU' }}</div>@endif
    <h2>{{ $invoice['item_name'] ?? 'Barang' }}</h2>
    <p>{{ $invoice['owner']['name'] ?? 'Pemilik' }} / {{ $invoice['renter']['name'] ?? 'Penyewa' }}</p>
    <p>Periode {{ \Illuminate\Support\Carbon::parse($details['start_date'])->format('d M Y') }} - {{ \Illuminate\Support\Carbon::parse($details['end_date'])->format('d M Y') }}</p>
    <table style="margin-top:18px"><thead><tr><th>Pembayaran</th><th>Metode / Pemeriksa</th><th class="amount">Nominal</th></tr></thead><tbody>
        @foreach($receipt['payments'] as $payment)
            <tr><td>{{ match($payment['kind']) {'deposit' => 'DP', 'balance' => 'Pelunasan', 'cash_balance' => 'Pelunasan tunai', default => 'Lunas'} }}<br><span class="muted">{{ $payment['transferred_at'] ? \Illuminate\Support\Carbon::parse($payment['transferred_at'])->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i').' WIB' : '' }}</span></td><td>{{ $payment['method']['label'] ?? ucfirst($payment['channel']) }}<br><span class="muted">{{ $payment['reviewer'] ?? 'Pemilik' }}</span></td><td class="amount">{{ $money($payment['amount']) }}</td></tr>
        @endforeach
        <tr class="total"><td colspan="2">Total terverifikasi</td><td class="amount">{{ $money($details['total_amount']) }}</td></tr>
    </tbody></table>
    <div class="note">Dokumen ini mencatat konfirmasi manual oleh pemilik barang. Pinjemin tidak terhubung dengan bank, e-wallet, QRIS, atau payment gateway dan tidak memvalidasi transaksi secara otomatis.</div>
</body>
</html>
