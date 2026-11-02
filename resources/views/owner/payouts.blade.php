@extends('layouts.owner')
@section('title', 'Pencairan Saya - Pinjemin')
@section('content')
<section class="page-shell">
    <div class="page-header"><div><p class="eyebrow">Pembayaran melalui Pinjemin</p><h1 class="mt-2 text-3xl font-semibold">Pencairan Saya</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-600">Hak pemilik setelah komisi 5% dari biaya sewa. Ongkir diteruskan tanpa komisi. Admin mentransfer setelah pengembalian barang terkonfirmasi dan tidak ada masalah terbuka.</p></div><x-button :href="route('payment-methods.index')" variant="outline">Atur Rekening Pencairan</x-button></div>
    <dl class="mt-6 grid gap-4 sm:grid-cols-2"><div class="card p-5"><dt class="text-sm text-zinc-500">Sudah ditransfer</dt><dd class="mt-2 text-xl font-semibold text-brand"><x-price :amount="$paidAmount" suffix="" /></dd></div><div class="card p-5"><dt class="text-sm text-zinc-500">Potensi pencairan belum ditransfer</dt><dd class="mt-2 text-xl font-semibold"><x-price :amount="$pendingAmount" suffix="" /></dd><p class="mt-1 text-xs text-zinc-500">Termasuk booking yang pembayarannya belum lunas atau sewanya belum selesai.</p></div></dl>
    <div class="mt-6 grid gap-4">
        @forelse($payouts as $payout)
            <article class="card p-5"><div class="flex flex-wrap justify-between gap-3"><div><a href="{{ route('incoming-bookings.show', $payout->booking) }}" class="font-semibold text-brand">{{ $payout->booking->booking_code }}</a><p class="mt-1 text-sm">{{ $payout->booking->item->name }}</p></div><span class="badge bg-blue-50 text-blue-700">{{ $payout->statusLabel() }}</span></div><div class="mt-4 flex flex-wrap justify-between gap-3 text-sm"><p>Hak bersih: <strong><x-price :amount="$payout->status === 'cancelled' ? 0 : $payout->amount" suffix="" /></strong> · Komisi: <x-price :amount="$payout->status === 'cancelled' ? 0 : $payout->commission_amount" suffix="" /></p><a href="{{ route('incoming-bookings.show', $payout->booking) }}" class="font-semibold text-brand">Lihat rincian &amp; bukti →</a></div></article>
        @empty<p class="card p-6 text-sm text-zinc-600">Belum ada pencairan. Rincian muncul setelah booking melalui Pinjemin diterima.</p>@endforelse
    </div><div class="mt-6">{{ $payouts->links() }}</div>
</section>
@endsection
