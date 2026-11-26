@extends('layouts.owner')

@section('title', 'Pemesanan Masuk - Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="['Dasbor Pemilik' => route('owner.dashboard'), 'Pemesanan Masuk' => null]" />
        <x-owner-heading title="Pemesanan Masuk" description="Tinjau pengajuan sewa dan pantau pengembalian barang." class="mt-4" />

        @php
            $filterSelects = ['status' => ['label' => 'Status', 'all' => 'Semua status', 'options' => $statuses]];
        @endphp
        <x-list-filters compact class="owner-list-toolbar" :action="route('incoming-bookings.index')" :filters="$filters" search-label="Cari pemesanan" placeholder="Kode, barang, atau nama penyewa" :selects="$filterSelects" />
        <x-list-results noun="pemesanan" :results="$bookings" :total-count="$totalCount" :filters="$filters" :selects="$filterSelects" :action="route('incoming-bookings.index')" empty-title="Belum ada pemesanan masuk" empty-description="Pengajuan sewa untuk barang Anda akan tampil di sini." />

        @if ($bookings->isNotEmpty())
            <div class="owner-records mt-4">
                <div class="owner-record-labels owner-booking-grid" aria-hidden="true">
                    <span>Pemesanan</span><span>Periode sewa</span><span>Tagihan</span><span class="text-center">Status</span><span class="text-right">Tindakan</span>
                </div>
                <ul class="divide-y divide-zinc-200">
                    @foreach ($bookings as $booking)
                        @php
                            $detailUrl = route('incoming-bookings.show', $booking);
                            $waitingForOwner = $booking->status === \App\BookingStatus::Pending;
                            $remaining = $booking->remainingAmount();
                        @endphp
                        <li class="owner-booking-grid owner-record-row" data-owner-booking-row>
                            <div class="owner-record-product flex min-w-0 items-center gap-3">
                                <x-item-image :item="$booking->item" class="h-14 max-w-17 shrink-0" placeholder="Tanpa foto" />
                                <div class="min-w-0">
                                    <p class="text-xs wrap-anywhere text-zinc-600">{{ $booking->booking_code }}</p>
                                    <h2 class="mt-0.5 font-semibold wrap-anywhere text-zinc-950"><a href="{{ $detailUrl }}" class="hover:text-brand-700 hover:underline">{{ $booking->item->name }}</a></h2>
                                    <p class="mt-0.5 text-sm wrap-anywhere text-zinc-600">{{ $booking->renter->name }}</p>
                                </div>
                            </div>
                            <div class="owner-record-date min-w-0 text-sm">
                                <p class="owner-record-mobile-label">Periode sewa</p>
                                <p class="text-zinc-900">{{ $booking->start_date->locale('id')->translatedFormat($booking->start_date->year === $booking->end_date->year ? 'd M' : 'd M Y') }} – {{ $booking->end_date->locale('id')->translatedFormat('d M Y') }}</p>
                                <p class="mt-0.5 text-xs text-zinc-600">{{ $booking->quantity }} unit <span aria-hidden="true">·</span> {{ $booking->rental_days }} hari</p>
                            </div>
                            <div class="owner-record-amount min-w-0 text-sm">
                                <p class="owner-record-mobile-label">Tagihan</p>
                                <x-price :amount="$booking->total_amount" suffix="" class="font-semibold wrap-anywhere text-zinc-950 tabular-nums" />
                                @if (($booking->accepted_at || $booking->payments->isNotEmpty()) && $remaining > 0)
                                    <p class="mt-0.5 text-xs text-zinc-600">Sisa <x-price :amount="$remaining" suffix="" /></p>
                                @endif
                            </div>
                            <div class="owner-record-status min-w-0 text-center"><x-booking-status-badge :status="$booking->status" :booking="$booking" class="owner-status-chip" /></div>
                            <div class="owner-record-actions">
                                <x-button :href="$detailUrl" :variant="$waitingForOwner ? 'primary' : 'outline'" class="w-full px-2" :aria-label="($waitingForOwner ? 'Tinjau Pengajuan ' : 'Detail ').$booking->booking_code">
                                    {{ $waitingForOwner ? 'Tinjau Pengajuan' : 'Detail' }}
                                </x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-5">{{ $bookings->links() }}</div>
    </section>
@endsection
