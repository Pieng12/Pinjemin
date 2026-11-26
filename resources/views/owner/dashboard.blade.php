@extends('layouts.owner')

@section('title', 'Dasbor Pemilik - Pinjemin')

@section('content')
    <section class="page-shell">
        <x-owner-heading title="Dasbor Pemilik">
            <x-button href="#kinerja-usaha" variant="outline">Lihat Kinerja</x-button>
            <x-button :href="route('my-items.create')"><i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i> Tambah Barang</x-button>
        </x-owner-heading>

        <dl class="mt-5 grid grid-cols-3 divide-x divide-zinc-200 rounded-lg border border-zinc-200 bg-white">
            @foreach ([
                ['Barang aktif', $activeItemsCount, 'Tampil di marketplace', route('my-items.index', ['status' => 'active'])],
                ['Perlu tindakan', $activity['bookingCount'], 'Pemesanan', '#tindakan-pemilik'],
                ['Total barang', $totalItemsCount, $inactiveItemsCount.' tidak aktif', route('my-items.index')],
            ] as [$label, $value, $info, $url])
                <div class="min-w-0 p-3 sm:p-4">
                    <dt><a href="{{ $url }}" class="text-xs font-semibold text-brand-800 hover:underline sm:text-sm">{{ $label }}</a></dt>
                    <dd class="mt-1 text-xl font-semibold text-zinc-950 tabular-nums sm:text-2xl">{{ number_format($value, 0, ',', '.') }}</dd>
                    <dd class="mt-1 text-xs text-zinc-600">{{ $info }}</dd>
                </div>
            @endforeach
        </dl>

        <x-activity-list :activity="$activity" title="Perlu Ditangani" compact id="tindakan-pemilik" class="scroll-mt-24" />

        @include('owner.partials.business-report', ['report' => $businessReport])

        <div class="mt-6 grid grid-cols-1 items-start gap-4 xl:grid-cols-2">
            <div class="card card-pad min-w-0">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-zinc-950">Pemesanan Terbaru</h2>
                    <a href="{{ route('incoming-bookings.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Lihat semua</a>
                </div>
                <div class="mt-3 grid min-w-0 grid-cols-1 divide-y divide-zinc-200">
                    @forelse ($recentIncomingBookings as $booking)
                        <a href="{{ route('incoming-bookings.show', $booking) }}" class="grid min-w-0 grid-cols-[minmax(0,1fr)_minmax(7rem,10rem)] items-center gap-3 py-3 transition-colors hover:bg-zinc-50">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-zinc-950">{{ $booking->item->name }}</p>
                                <p class="mt-1 text-sm wrap-anywhere text-zinc-600">{{ $booking->renter->name }} · {{ $booking->start_date->locale('id')->translatedFormat('d M') }}</p>
                            </div>
                            <div class="text-center"><x-booking-status-badge :status="$booking->status" :booking="$booking" class="h-10 w-full justify-center text-center leading-tight" /></div>
                        </a>
                    @empty
                        <x-empty-state :show-mark="false" :framed="false" title="Belum ada permintaan sewa" description="Pemesanan untuk barang Anda akan tampil di sini." />
                    @endforelse
                </div>
            </div>

            <div class="card card-pad min-w-0">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-zinc-950">Barang Terbaru</h2>
                    <a href="{{ route('my-items.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Kelola barang</a>
                </div>
                <div class="mt-3 grid min-w-0 grid-cols-1 divide-y divide-zinc-200">
                    @forelse ($recentItems as $item)
                        <a href="{{ route('my-items.show', $item) }}" class="flex min-w-0 items-center gap-3 py-3 transition-colors hover:bg-zinc-50">
                            <x-item-image :item="$item" class="h-14 max-w-20 shrink-0" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-zinc-950">{{ $item->name }}</p>
                                <p class="mt-1 text-sm text-zinc-600">{{ $item->city }} · {{ $item->statusLabel() }}</p>
                            </div>
                        </a>
                    @empty
                        <x-empty-state :show-mark="false" :framed="false" title="Belum ada barang" description="Tambahkan barang pertama untuk mulai menyewakan." action-label="Tambah Barang" :action-url="route('my-items.create')" />
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection
