@extends('layouts.admin')

@section('title', 'Booking - Admin Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="[
            'Admin' => route('admin.dashboard'),
            'Booking' => null,
        ]" />

        <div class="page-header mt-6">
            <div>
                <p class="eyebrow">Transaksi platform</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-zinc-950">Booking</h1>
                <p class="mt-2 text-zinc-600">Daftar booking dari seluruh pengguna Pinjemin.</p>
            </div>
        </div>

        <form method="GET" class="soft-card mt-6 grid gap-3 p-3 md:grid-cols-[1fr_220px_auto]">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari kode, barang, atau penyewa" class="input">
            <select name="status" class="input">
                <option value="">Semua Status</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-button type="submit">Filter</x-button>
        </form>

        <div class="mt-8 table-shell">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="table-head">
                        <tr>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Barang</th>
                            <th class="px-4 py-3">Pemilik</th>
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
                                <td class="px-4 py-3"><x-booking-status-badge :status="$booking->status" :booking="$booking" /></td>
                                <td class="px-4 py-3 text-right"><x-button href="{{ route('admin.bookings.show', $booking) }}" variant="outline">Detail</x-button></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10">
                                    <x-empty-state title="Belum ada booking" description="Booking pengguna akan tampil di sini." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $bookings->links() }}</div>
    </section>
@endsection
