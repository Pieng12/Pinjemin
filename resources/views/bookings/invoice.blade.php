<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>INV-{{ $booking->booking_code }}</title>
    <style>
        @page { margin: 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #27272a; line-height: 1.6; letter-spacing: 0; }
        h1 { font-size: 26px; margin: 0; color: #1d4ed8; }
        h2 { font-size: 13px; margin: 0 0 8px; }
        p { margin: 3px 0; overflow-wrap: break-word; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 18px; margin-bottom: 24px; }
        .muted { color: #71717a; }
        .cancelled { color: #b91c1c; background: #fef2f2; padding: 10px; margin-bottom: 16px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { vertical-align: top; padding: 10px 0; text-align: left; overflow-wrap: break-word; }
        .parties td { width: 50%; padding-right: 16px; }
        .costs { margin-top: 18px; }
        .costs td { border-bottom: 1px solid #e4e4e7; }
        .amount { text-align: right; width: 32%; }
        .total { font-weight: bold; color: #1d4ed8; font-size: 14px; }
        .section { margin-top: 22px; }
        .note { margin-top: 28px; border-top: 1px solid #e4e4e7; padding-top: 14px; font-size: 10px; color: #71717a; }
    </style>
</head>
<body>
    @php($details = $invoice['booking'])
    @php($money = fn ($amount) => 'Rp '.number_format((float) $amount, (float) $amount === floor((float) $amount) ? 0 : 2, ',', '.'))
    <div class="header">
        <h1>Pinjemin.</h1>
        <h2>Tagihan Penyewaan</h2>
        <p>INV-{{ $details['booking_code'] }}</p>
        <p class="muted">Diterima: {{ ($details['accepted_at'] ?? $details['approved_at'] ?? null) ? \Illuminate\Support\Carbon::parse($details['accepted_at'] ?? $details['approved_at'])->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i').' WIB' : 'Waktu belum tercatat' }}</p>
    </div>
    @if($booking->status === \App\BookingStatus::Cancelled)<div class="cancelled">DIBATALKAN{{ $booking->cancelled_at ? ' - '.$booking->cancelled_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i').' WIB' : '' }}</div>@endif
    <table class="parties"><tr>
        <td><h2>Pemilik</h2><p>{{ $invoice['owner']['name'] ?? 'Belum tercatat' }}</p><p>{{ $invoice['owner']['email'] ?? '' }}</p><p>{{ $invoice['owner']['phone'] ?? '' }}</p></td>
        <td><h2>Penyewa</h2><p>{{ $invoice['renter']['name'] ?? 'Belum tercatat' }}</p><p>{{ $invoice['renter']['email'] ?? '' }}</p><p>{{ $invoice['renter']['phone'] ?? '' }}</p></td>
    </tr></table>
    <div class="section">
        <h2>{{ $invoice['item_name'] ?? 'Barang' }}</h2>
        <p>Periode: {{ \Illuminate\Support\Carbon::parse($details['start_date'])->format('d M Y') }} - {{ \Illuminate\Support\Carbon::parse($details['end_date'])->format('d M Y') }}</p>
        <p><strong>Tanggal Pengembalian: {{ \Illuminate\Support\Carbon::parse($details['end_date'])->format('d M Y') }}</strong></p>
        <p>{{ $details['quantity'] }} unit / {{ $details['rental_days'] }} hari</p>
        <p>Cara penerimaan: {{ $details['fulfillment_method'] === 'delivery' ? 'Dikirim ke penyewa' : 'Ambil sendiri' }}</p>
    </div>
    <table class="costs">
        <tr><td>Sewa: {{ $money($details['daily_price']) }} x {{ $details['rental_days'] }} hari x {{ $details['quantity'] }} unit</td><td class="amount">{{ $money($details['subtotal']) }}</td></tr>
        <tr><td>Uang muka (DP)</td><td class="amount">{{ $money($details['deposit_amount']) }}</td></tr>
        <tr><td>Ongkir ke penyewa</td><td class="amount">{{ $money($details['delivery_fee']) }}</td></tr>
        <tr class="total"><td>Total</td><td class="amount">{{ $money($details['total_amount']) }}</td></tr>
    </table>
    <div class="section"><h2>Status Pembayaran</h2><p>Terverifikasi: {{ $money($booking->verifiedAmount()) }}</p><p>Sisa pembayaran: {{ $money($booking->remainingAmount()) }}</p></div>
    @if($details['fulfillment_method'] === 'delivery')
        <div class="section"><h2>Tujuan Pengiriman</h2><p>{{ $invoice['recipient']['name'] ?? 'Belum tercatat' }} / {{ $invoice['recipient']['phone'] ?? '' }}</p><p style="white-space: pre-line">{{ $invoice['recipient']['address'] ?? 'Belum tercatat' }}</p><p>{{ $invoice['recipient']['city'] ?? '' }}</p></div>
    @endif
    <div class="section"><h2>Lokasi Pengambilan / Pengembalian</h2><p style="white-space: pre-line">{{ $invoice['pickup']['address'] ?? 'Alamat belum tercatat' }}</p><p>{{ $invoice['pickup']['city'] ?? '' }}</p></div>
    <div class="note">Tagihan ini mencatat biaya penyewaan dan bukan bukti pembayaran. Verifikasi pembayaran dilakukan manual oleh pemilik, bukan oleh bank atau payment gateway. @if($details['fulfillment_method'] === 'delivery')Ongkir hanya mencakup pengiriman ke penyewa; pengembalian menjadi tanggung jawab penyewa.@endif @if($invoice['legacy'] ?? false)Dokumen booking lama menggunakan data yang tersedia saat fitur invoice ditambahkan.@endif</div>
</body>
</html>
