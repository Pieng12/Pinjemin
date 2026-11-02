<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>OUT-{{ $booking->booking_code }}</title><style>@page { margin:36px; } body {font-family:DejaVu Sans,sans-serif;font-size:11px;color:#27272a;line-height:1.7}h1{color:#005f73}table{width:100%;border-collapse:collapse}td{padding:10px 0;border-bottom:1px solid #ddd}.amount{text-align:right}.note{margin-top:25px;color:#666}</style></head><body>
@php($money = fn($value) => 'Rp '.number_format((float)$value, 2, ',', '.'))
<h1>Pinjemin.</h1><h2>Rincian Pencairan Pemilik</h2><p>OUT-{{ $booking->booking_code }}</p>
<p>Booking: {{ $booking->booking_code }}<br>Pemilik: {{ $booking->fulfillment_snapshot['owner']['name'] ?? $booking->item->user->name }}<br>Barang: {{ $booking->fulfillment_snapshot['item_name'] ?? $booking->item->name }}</p>
<table><tr><td>Biaya sewa</td><td class="amount">{{ $money($booking->subtotal) }}</td></tr><tr><td>Ongkir</td><td class="amount">{{ $money($booking->delivery_fee) }}</td></tr><tr><td>Komisi platform {{ $payout->commission_rate }}% dari sewa</td><td class="amount">-{{ $money($payout->commission_amount) }}</td></tr><tr><td><strong>Ditransfer ke pemilik</strong></td><td class="amount"><strong>{{ $money($payout->amount) }}</strong></td></tr></table>
<p>Tujuan: {{ $payout->destination_snapshot['label'] }} / {{ $payout->destination_snapshot['account_name'] }} / {{ $payout->destination_snapshot['account_identifier'] }}<br>Waktu: {{ $payout->paid_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB<br>Referensi: {{ $payout->transaction_reference }}</p>
<p class="note">Pencairan dicatat manual oleh admin Pinjemin. Dokumen ini tidak merupakan verifikasi otomatis dari bank. Biaya transfer pencairan ditanggung Pinjemin.</p>
</body></html>
