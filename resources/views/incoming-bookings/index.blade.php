@extends('layouts.owner')

@section('title', 'Booking Masuk - Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="[
            'Dashboard Pemilik' => route('owner.dashboard'),
            'Booking Masuk' => null,
        ]" />

        <div class="page-header mt-6">
            <div>
                <p class="eyebrow">Permintaan peminjam</p>
                <h1 class="mt-2 text-3xl font-semibold text-zinc-950">Booking Masuk</h1>
                <p class="mt-2 text-zinc-600">Permintaan sewa untuk barang milikmu.</p>
            </div>
            <form method="GET" class="flex w-full items-center gap-2 sm:w-auto">
                <select name="status" class="input mt-0 min-w-0 flex-1 sm:w-48">
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
                            <th class="px-4 py-3">Penyewa</th>
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
                                <td class="px-4 py-3 text-zinc-600">{{ $booking->renter->name }}</td>
                                <td class="px-4 py-3 text-zinc-600">{{ $booking->start_date->format('d M Y') }} - {{ $booking->end_date->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-zinc-600">{{ $booking->quantity }}</td>
                                <td class="px-4 py-3">
                                    <x-price :amount="$booking->total_amount" suffix="" />
                                    @if($booking->accepted_at || $booking->payments->isNotEmpty())
                                        <p class="mt-1 text-xs text-zinc-500">Terverifikasi <x-price :amount="$booking->verifiedAmount()" suffix="" /> / sisa <x-price :amount="$booking->remainingAmount()" suffix="" /></p>
                                    @endif
                                </td>
                                <td class="px-4 py-3"><x-booking-status-badge :status="$booking->status" :booking="$booking" /></td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <x-button href="{{ route('incoming-bookings.show', $booking) }}" variant="outline">Detail</x-button>
                                        @if ($booking->status === \App\BookingStatus::Pending && ($booking->fulfillment_method !== 'delivery' || ($booking->fulfillment_snapshot['free_delivery'] ?? false)))
                                            <form method="POST" action="{{ route('incoming-bookings.approve', $booking) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-button type="submit" variant="secondary">Terima</x-button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10">
                                    <x-empty-state :show-mark="false" :framed="false" title="Belum ada booking masuk" description="Permintaan sewa untuk barangmu akan tampil di sini." />
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
                                <p class="mt-2 text-sm text-zinc-600">{{ $booking->renter->name }} / {{ $booking->start_date->format('d M Y') }} - {{ $booking->end_date->format('d M Y') }}</p>
                                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                    <div><x-price :amount="$booking->total_amount" suffix="" class="font-bold text-blue-700" />@if($booking->accepted_at || $booking->payments->isNotEmpty())<p class="mt-1 text-xs text-zinc-500">Sisa <x-price :amount="$booking->remainingAmount()" suffix="" /></p>@endif</div>
                                    <div class="flex gap-2">
                                        <x-button href="{{ route('incoming-bookings.show', $booking) }}" variant="outline">Detail</x-button>
                                        @if ($booking->status === \App\BookingStatus::Pending && ($booking->fulfillment_method !== 'delivery' || ($booking->fulfillment_snapshot['free_delivery'] ?? false)))
                                            <form method="POST" action="{{ route('incoming-bookings.approve', $booking) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-button type="submit" variant="secondary">Terima</x-button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="p-4">
                        <x-empty-state :show-mark="false" :framed="false" title="Belum ada booking masuk" description="Permintaan sewa untuk barangmu akan tampil di sini." />
                    </div>
                @endforelse
            </div>
        </div>

        <div class="mt-6">{{ $bookings->links() }}</div>
    </section>
@endsection
