@extends('layouts.owner')

@section('title', 'Dashboard Pemilik - Pinjemin')

@section('content')
    <section class="page-shell">
        <div class="page-header">
            <div>
                <p class="eyebrow">Mode Pemilik</p>
                <h1 class="mt-2 text-3xl font-semibold text-zinc-950">Dashboard Pemilik</h1>
                <p class="mt-2 text-zinc-600">Kelola barang dan permintaan sewa dari satu tempat.</p>
            </div>
            <x-button :href="route('my-items.create')"><i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i> Tambah Barang</x-button>
        </div>

        <div class="mt-7 grid grid-cols-3 gap-2 sm:gap-4">
            <x-stat-card class="owner-stat-card" label="Barang Aktif" :value="$activeItemsCount" description="Tampil di marketplace" :href="route('my-items.index')" />
            <x-stat-card class="owner-stat-card" label="Perlu Tindakan" :value="$pendingBookingsCount" description="Booking, pembayaran, atau refund" :href="route('incoming-bookings.index')" />
            <x-stat-card class="owner-stat-card" label="Total Barang" :value="$totalItemsCount" description="{{ $inactiveItemsCount }} tidak aktif" :href="route('my-items.index')" />
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-2">
            <div class="card card-pad">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-zinc-950">Permintaan Sewa Terbaru</h2>
                    <a href="{{ route('incoming-bookings.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Lihat semua</a>
                </div>
                <div class="mt-5 grid gap-3">
                    @forelse ($recentIncomingBookings as $booking)
                        <a href="{{ route('incoming-bookings.show', $booking) }}" class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 p-3 transition hover:border-blue-200 hover:bg-zinc-50">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-zinc-950">{{ $booking->item->name }}</p>
                                <p class="mt-1 text-sm text-zinc-600">{{ $booking->renter->name }} / {{ $booking->start_date->format('d M Y') }}</p>
                            </div>
                            <x-booking-status-badge :status="$booking->status" :booking="$booking" />
                        </a>
                    @empty
                        <x-empty-state :show-mark="false" :framed="false" title="Belum ada permintaan sewa" description="Booking untuk barangmu akan tampil di sini." />
                    @endforelse
                </div>
            </div>

            <div class="card card-pad">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-zinc-950">Barang Terbaru Saya</h2>
                    <a href="{{ route('my-items.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Kelola barang</a>
                </div>
                <div class="mt-5 grid gap-3">
                    @forelse ($recentItems as $item)
                        <a href="{{ route('my-items.show', $item) }}" class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 transition hover:border-blue-200 hover:bg-zinc-50">
                            <x-item-image :item="$item" class="h-14 max-w-20" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-zinc-950">{{ $item->name }}</p>
                                <p class="mt-1 text-sm text-zinc-600">{{ $item->city }} / {{ $item->statusLabel() }}</p>
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
