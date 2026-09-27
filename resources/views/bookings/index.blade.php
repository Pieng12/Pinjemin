@extends('layouts.app')

@section('title', 'Penyewaan Saya - Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="[
            'Ringkasan Akun' => route('dashboard'),
            'Penyewaan Saya' => null,
        ]" />

        <div class="page-header mt-6">
            <div>
                <p class="eyebrow">Aktivitas sewa</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-zinc-950">Penyewaan Saya</h1>
                <p class="mt-2 text-zinc-600">Riwayat barang yang kamu ajukan untuk disewa.</p>
            </div>
            <form method="GET" class="soft-card flex w-full gap-2 p-2 sm:w-auto">
                <select name="status" class="input min-w-0 flex-1 sm:w-48">
                    <option value="">Semua Status</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-button type="submit">Filter</x-button>
            </form>
        </div>

        <div class="mt-8 table-shell">
            <div class="hidden overflow-x-auto lg:block">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="table-head">
                        <tr>
                            <th class="px-4 py-3">Booking</th>
                            <th class="px-4 py-3">Barang</th>
                            <th class="px-4 py-3">Pemilik</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Qty</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($bookings as $booking)
                            <tr class="table-row">
                                <td class="px-4 py-3 font-medium text-zinc-950">{{ $booking->booking_code }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-item-image :item="$booking->item" class="h-14 max-w-20" />
                                        <span class="font-medium text-zinc-950">{{ $booking->item->name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-zinc-600">{{ $booking->item->user->name }}</td>
                                <td class="px-4 py-3 text-zinc-600">{{ $booking->start_date->format('d M Y') }} - {{ $booking->end_date->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-zinc-600">{{ $booking->quantity }}</td>
                                <td class="px-4 py-3">
                                    <x-price :amount="$booking->total_amount" suffix="" />
                                    @if($booking->accepted_at || $booking->payments->isNotEmpty())
                                        <p class="mt-1 text-xs text-zinc-500">Sisa <x-price :amount="$booking->remainingAmount()" suffix="" /></p>
                                    @endif
                                </td>
                                <td class="px-4 py-3"><x-booking-status-badge :status="$booking->status" :booking="$booking" /></td>
                                <td class="px-4 py-3 text-right">
                                    <x-button href="{{ route('my-bookings.show', $booking) }}" variant="outline">Detail</x-button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10">
                                    <x-empty-state title="Belum ada penyewaan" description="Pengajuan sewa yang kamu buat akan tampil di sini." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-zinc-200 lg:hidden">
                @forelse ($bookings as $booking)
                    <article class="p-4">
                        <div class="flex gap-3">
                            <x-item-image :item="$booking->item" class="h-20 max-w-24" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $booking->booking_code }}</p>
                                        <h2 class="mt-1 font-semibold text-zinc-950">{{ $booking->item->name }}</h2>
                                    </div>
                                    <x-booking-status-badge :status="$booking->status" :booking="$booking" />
                                </div>
                                <p class="mt-2 text-sm text-zinc-600">{{ $booking->start_date->format('d M Y') }} - {{ $booking->end_date->format('d M Y') }}</p>
                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <div><x-price :amount="$booking->total_amount" suffix="" class="font-bold text-blue-700" />@if($booking->accepted_at || $booking->payments->isNotEmpty())<p class="mt-1 text-xs text-zinc-500">Sisa <x-price :amount="$booking->remainingAmount()" suffix="" /></p>@endif</div>
                                    <x-button href="{{ route('my-bookings.show', $booking) }}" variant="outline">Detail</x-button>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="p-4">
                        <x-empty-state title="Belum ada penyewaan" description="Pengajuan sewa yang kamu buat akan tampil di sini." />
                    </div>
                @endforelse
            </div>
        </div>

        <div class="mt-6">{{ $bookings->links() }}</div>
    </section>
@endsection
