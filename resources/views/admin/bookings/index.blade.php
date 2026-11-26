@extends('layouts.admin')

@section('title', 'Pemesanan - Administrator Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="[
            'Administrator' => route('admin.dashboard'),
            'Pemesanan' => null,
        ]" />

        <div class="page-header mt-6">
            <div>
                <p class="eyebrow">Transaksi platform</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-zinc-950">Pemesanan</h1>
                <p class="mt-2 text-zinc-600">Daftar pemesanan dari seluruh pengguna Pinjemin.</p>
            </div>
        </div>

        @php
            $filterSelects = ['status' => ['label' => 'Status', 'all' => 'Semua status', 'options' => $statuses]];
        @endphp
        <x-list-filters :action="route('admin.bookings.index')" :filters="$filters" search-label="Cari pemesanan" placeholder="Kode, barang, pemilik, atau penyewa" :selects="$filterSelects" />
        <x-list-results :results="$bookings" :total-count="$totalCount" :filters="$filters" :selects="$filterSelects" :action="route('admin.bookings.index')" empty-title="Belum ada pemesanan" empty-description="Pemesanan pengguna akan tampil di sini." />

        @if ($bookings->isNotEmpty())
            <div class="@container mt-8 table-shell">
                <div class="hidden overflow-x-auto @min-[68rem]:block">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm">
                        <thead class="table-head">
                            <tr>
                                <th class="px-4 py-3">Kode</th>
                                <th class="px-4 py-3">Barang</th>
                                <th class="px-4 py-3">Pemilik</th>
                                <th class="px-4 py-3">Penyewa</th>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Jumlah</th>
                                <th class="px-4 py-3">Total</th>
                                <th class="min-w-44 px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @foreach ($bookings as $booking)
                                <tr class="table-row">
                                    <td class="px-4 py-3 font-medium text-zinc-950">{{ $booking->booking_code }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <x-item-image :item="$booking->item" class="h-14 max-w-20" />
                                            <span class="font-medium text-zinc-950">{{ $booking->item->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-zinc-600">{{ $booking->item->user->name }}</td>
                                    <td class="px-4 py-3 text-zinc-600">{{ $booking->renter->name }}</td>
                                    <td class="px-4 py-3 text-zinc-600">{{ $booking->start_date->format('d M Y') }} - {{ $booking->end_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-zinc-600">{{ $booking->quantity }}</td>
                                    <td class="px-4 py-3">
                                        <x-price :amount="$booking->total_amount" suffix="" />
                                        @if($booking->accepted_at || $booking->payments->isNotEmpty())
                                            <p class="mt-1 text-xs text-zinc-500">Terverifikasi <x-price :amount="$booking->verifiedAmount()" suffix="" /> / sisa <x-price :amount="$booking->remainingAmount()" suffix="" /></p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center"><x-booking-status-badge :status="$booking->status" :booking="$booking" /></td>
                                    <td class="px-4 py-3 text-right"><x-button href="{{ route('admin.bookings.show', $booking) }}" variant="outline">Detail</x-button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="divide-y divide-zinc-200 @min-[68rem]:hidden">
                    @foreach ($bookings as $booking)
                        <article class="space-y-4 p-4">
                            <div class="flex items-start gap-3">
                                <x-item-image :item="$booking->item" class="h-20 max-w-24 shrink-0" />
                                <div class="min-w-0 space-y-2">
                                    <p class="break-all text-xs font-semibold text-zinc-500">{{ $booking->booking_code }}</p>
                                    <h2 class="break-words font-semibold text-zinc-950">{{ $booking->item->name }}</h2>
                                    <x-booking-status-badge :status="$booking->status" :booking="$booking" />
                                </div>
                            </div>
                            <dl class="grid grid-cols-2 gap-3 text-sm">
                                <div class="min-w-0"><dt class="text-zinc-500">Pemilik</dt><dd class="break-words text-zinc-700">{{ $booking->item->user->name }}</dd></div>
                                <div class="min-w-0"><dt class="text-zinc-500">Penyewa</dt><dd class="break-words text-zinc-700">{{ $booking->renter->name }}</dd></div>
                                <div class="col-span-2"><dt class="text-zinc-500">Tanggal</dt><dd class="text-zinc-700">{{ $booking->start_date->format('d M Y') }} - {{ $booking->end_date->format('d M Y') }}</dd></div>
                                <div><dt class="text-zinc-500">Jumlah</dt><dd class="text-zinc-700">{{ $booking->quantity }} unit</dd></div>
                                <div><dt class="text-zinc-500">Total</dt><dd><x-price :amount="$booking->total_amount" suffix="" /></dd></div>
                            </dl>
                            @if ($booking->accepted_at || $booking->payments->isNotEmpty())
                                <p class="text-xs text-zinc-500">Terverifikasi <x-price :amount="$booking->verifiedAmount()" suffix="" /> / sisa <x-price :amount="$booking->remainingAmount()" suffix="" /></p>
                            @endif
                            <x-button :href="route('admin.bookings.show', $booking)" variant="outline">Detail</x-button>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-6">{{ $bookings->links() }}</div>
    </section>
@endsection
