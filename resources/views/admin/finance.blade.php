@extends('layouts.admin')
@section('title', 'Keuangan & Pencairan - Pinjemin')
@section('content')
<section class="page-shell">
    <div class="page-header"><div><p class="eyebrow">Pembayaran manual</p><h1 class="mt-2 text-3xl font-semibold">Keuangan &amp; Pencairan</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-600">Periksa mutasi rekening sebelum memverifikasi. Transfer ke pemilik dilakukan manual setelah pengembalian terkonfirmasi. Komisi 5% hanya dari biaya sewa.</p></div><x-button :href="route('admin.payment-methods.index')" variant="outline">Rekening Pinjemin</x-button></div>
    <dl class="mt-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-5">
        @foreach(['Dana masuk terverifikasi' => $received, 'Dana masih ditahan' => $held, 'Ditransfer ke pemilik' => $paid, 'Pengembalian Dana tercatat' => $refunded, 'Komisi sudah dicairkan' => $commission] as $label => $value)
            <div class="card min-w-0 p-4 last:col-span-2 xl:last:col-span-1"><dt class="text-sm text-zinc-600">{{ $label }}</dt><dd class="mt-2 break-words text-lg font-semibold"><x-price :amount="$value" suffix="" /></dd></div>
        @endforeach
    </dl>
    <p class="mt-3 text-xs leading-5 text-zinc-500">Ringkasan berdasarkan catatan pemesanan baru melalui Pinjemin, bukan saldo bank langsung. Dana ditahan mencakup DP, kewajiban pengembalian dana, hak pemilik, dan komisi yang belum dicairkan. Komisi ditampilkan sebelum biaya operasional.</p>
    <nav aria-label="Filter keuangan" class="mt-7 flex flex-wrap gap-2">
        @foreach(['all' => 'Semua', 'review' => 'Periksa Bukti', 'refund' => 'Pengembalian Dana', 'ready' => 'Siap Dicairkan', 'hold' => 'Ditahan / Diproses', 'paid' => 'Sudah Ditransfer'] as $value => $label)
            <a href="{{ route('admin.finance', ['tab' => $value]) }}" class="btn {{ $tab === $value ? 'btn-primary' : 'btn-outline' }}" @if($tab === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="mt-5 grid gap-4">
        @forelse($bookings as $booking)
            <article class="card flex flex-wrap items-center justify-between gap-4 p-5">
                <div class="min-w-0"><a href="{{ route('admin.bookings.show', $booking) }}" class="font-semibold text-brand">{{ $booking->booking_code }}</a><p class="mt-1 break-words text-sm">{{ $booking->item->name }} / {{ $booking->item->user->name }}</p><div class="mt-3 flex flex-wrap gap-2"><span class="badge bg-brand-50 text-brand-700">Pembayaran: {{ $booking->effectiveStatus()->label() }}</span><span class="badge bg-zinc-100 text-zinc-700">Pencairan: {{ $booking->payout->statusLabel() }}</span></div></div>
                <div class="text-sm"><p>Hak pemilik: <strong><x-price :amount="$booking->payout->amount" suffix="" /></strong></p><p class="mt-1 text-zinc-500">Komisi: <x-price :amount="$booking->payout->commission_amount" suffix="" /></p><x-button :href="route('admin.bookings.show', $booking)" variant="outline" class="mt-3">Periksa Pemesanan</x-button></div>
            </article>
        @empty
            <x-empty-state :show-mark="false" title="Tidak ada transaksi pada filter ini" description="Pilih filter lain untuk memeriksa transaksi yang tersedia." action-label="Lihat semua transaksi" :action-url="route('admin.finance')" />
        @endforelse
    </div>
    <div class="mt-6">{{ $bookings->links() }}</div>
</section>
@endsection
