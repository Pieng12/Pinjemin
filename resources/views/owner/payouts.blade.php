@extends('layouts.owner')

@section('title', 'Pencairan Saya - Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="['Dasbor Pemilik' => route('owner.dashboard'), 'Pencairan Saya' => null]" />
        <x-owner-heading title="Pencairan Saya" description="Dana ditransfer setelah pengembalian terkonfirmasi. Komisi 5% berlaku untuk biaya sewa. Biaya pengiriman diteruskan penuh." class="mt-4">
            <x-button :href="route('payment-methods.index')" variant="outline">Atur Rekening</x-button>
        </x-owner-heading>

        <dl class="mt-5 grid grid-cols-2 divide-x divide-zinc-200 rounded-lg border border-zinc-200 bg-white">
            <div class="min-w-0 p-3.5 sm:p-4">
                <dt class="text-sm font-medium text-zinc-600">Sudah ditransfer</dt>
                <dd class="mt-2 text-xl font-semibold wrap-anywhere text-brand-800 tabular-nums"><x-price :amount="$paidAmount" suffix="" /></dd>
            </div>
            <div class="min-w-0 p-3.5 sm:p-4">
                <dt class="text-sm font-medium text-zinc-600">Potensi pencairan</dt>
                <dd class="mt-2 text-xl font-semibold wrap-anywhere text-zinc-950 tabular-nums"><x-price :amount="$pendingAmount" suffix="" /></dd>
                <dd class="mt-1 text-xs leading-5 text-zinc-600">Termasuk sewa yang belum selesai atau belum lunas.</dd>
            </div>
        </dl>

        <section aria-labelledby="owner-payout-list" class="mt-6">
            <h2 id="owner-payout-list" class="text-lg font-semibold">Riwayat Pencairan</h2>
            @if ($payouts->isNotEmpty())
                <div class="owner-records mt-3">
                    <div class="owner-record-labels owner-payout-grid" aria-hidden="true">
                        <span>Pemesanan</span><span>Hak pemilik</span><span>Komisi</span><span class="text-center">Status</span><span class="text-right">Tindakan</span>
                    </div>
                    <ul class="divide-y divide-zinc-200">
                        @foreach ($payouts as $payout)
                            @php
                                $detailUrl = route('incoming-bookings.show', $payout->booking);
                                $cancelled = $payout->status === 'cancelled';
                            @endphp
                            <li class="owner-record-row owner-payout-grid">
                                <div class="owner-record-product min-w-0">
                                    <p class="text-xs wrap-anywhere text-zinc-600">{{ $payout->booking->booking_code }}</p>
                                    <a href="{{ $detailUrl }}" class="mt-0.5 inline-block font-semibold wrap-anywhere text-zinc-950 hover:text-brand-700 hover:underline">{{ $payout->booking->item->name }}</a>
                                    @if ($payout->paid_at)
                                        <p class="mt-0.5 text-xs text-zinc-600">Transfer {{ $payout->paid_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->locale('id')->translatedFormat('d M Y') }}</p>
                                    @endif
                                </div>
                                <div class="owner-record-amount min-w-0 text-sm">
                                    <p class="owner-record-mobile-label">Hak pemilik</p>
                                    <x-price :amount="$cancelled ? 0 : $payout->amount" suffix="" class="font-semibold text-zinc-950 tabular-nums" />
                                </div>
                                <div class="owner-record-commission min-w-0 text-sm wrap-anywhere text-zinc-700">
                                    <p class="owner-record-mobile-label">Komisi</p>
                                    <x-price :amount="$cancelled ? 0 : $payout->commission_amount" suffix="" />
                                </div>
                                <div class="owner-record-status">
                                    <span @class(['badge owner-status-chip', 'bg-emerald-50 text-emerald-800' => $payout->status === 'paid', 'bg-amber-50 text-amber-800' => $payout->status === 'on_hold', 'bg-brand-50 text-brand-800' => $payout->status === 'processing', 'bg-zinc-100 text-zinc-700' => in_array($payout->status, ['pending', 'cancelled'], true)])>{{ $payout->statusLabel() }}</span>
                                </div>
                                <div class="owner-record-actions"><x-button :href="$detailUrl.'#booking-payout'" variant="outline" class="w-full">Rincian</x-button></div>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="mt-5">{{ $payouts->links() }}</div>
            @else
                <p class="mt-3 rounded-lg border border-zinc-200 bg-white p-4 text-sm text-zinc-600">Rincian pencairan muncul setelah Anda menerima pemesanan melalui Pinjemin.</p>
            @endif
        </section>
    </section>
@endsection
