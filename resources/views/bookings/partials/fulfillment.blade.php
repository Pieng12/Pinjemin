@php($snapshot = $booking->fulfillment_snapshot ?? [])
<div class="mt-6 border-t border-zinc-200 pt-5 text-sm">
    <h2 class="font-semibold text-zinc-950">{{ $booking->fulfillment_method === 'delivery' ? 'Pengiriman ke Penyewa' : 'Pengambilan Langsung' }}</h2>
    @if($booking->fulfillment_method === 'delivery')
        <div class="mt-3 space-y-1 leading-6 text-zinc-700">
            <p class="font-medium">{{ $snapshot['recipient']['name'] ?? 'Belum tercatat' }}</p>
            <p>{{ $snapshot['recipient']['phone'] ?? 'Belum tercatat' }}</p>
            <p class="whitespace-pre-line break-words">{{ $snapshot['recipient']['address'] ?? 'Belum tercatat' }}</p>
            <p>{{ $snapshot['recipient']['city'] ?? 'Belum tercatat' }}</p>
        </div>
        <p class="mt-3 text-xs leading-5 text-zinc-500">Ongkir hanya untuk pengiriman ke penyewa. Pengembalian ke lokasi pemilik menjadi tanggung jawab penyewa.</p>
    @endif
    <p class="mt-3 font-medium text-zinc-700">Lokasi Pengambilan / Pengembalian</p>
    <p class="mt-1 text-zinc-600">{{ $snapshot['pickup']['city'] ?? $booking->item->city }}</p>
    @if($showPickup)
        <p class="mt-1 whitespace-pre-line break-words text-zinc-700">{{ $snapshot['pickup']['address'] ?? 'Alamat belum tercatat.' }}</p>
    @else
        <p class="mt-1 text-xs text-zinc-500">Alamat lengkap tersedia setelah persetujuan final.</p>
    @endif
</div>
