@extends('layouts.admin')

@section('title', 'Detail Booking - Admin Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="[
            'Admin' => route('admin.dashboard'),
            'Booking' => route('admin.bookings.index'),
            'Detail' => null,
        ]" />

        <div class="page-header mt-6">
            <div>
                <x-booking-status-badge :status="$booking->status" :booking="$booking" />
                <h1 class="mt-3 text-3xl font-bold tracking-tight text-zinc-950">Booking #{{ $booking->booking_code }}</h1>
                <p class="mt-2 text-zinc-600">{{ $booking->item->name }} / {{ $booking->created_at->format('d M Y H:i') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('downloadInvoice', $booking)<x-button :href="route('bookings.invoice', $booking)" variant="outline">Unduh Invoice</x-button>@endcan
                @can('downloadPaymentReceipt', $booking)<x-button :href="route('bookings.payment-receipt', $booking)" variant="outline">Konfirmasi Pembayaran</x-button>@endcan
                <x-button href="{{ route('admin.bookings.index') }}" variant="outline">Kembali</x-button>
            </div>
        </div>

        <div class="mt-8 grid gap-8 lg:grid-cols-2">
            <div class="card card-pad">
                <h2 class="text-lg font-bold text-zinc-950">Barang</h2>
                <div class="mt-4 flex gap-4">
                    <x-item-image :item="$booking->item" class="h-28 max-w-36" />
                    <div>
                        <p class="font-semibold text-zinc-950">{{ $booking->item->name }}</p>
                        <p class="mt-1 text-sm text-zinc-600">{{ $booking->item->category->name }} / {{ $booking->item->city }}</p>
                    </div>
                </div>
                <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="font-medium text-zinc-500">Pemilik</dt><dd class="mt-1 text-zinc-950">{{ $booking->item->user->name }}</dd></div>
                    <div><dt class="font-medium text-zinc-500">Penyewa</dt><dd class="mt-1 text-zinc-950">{{ $booking->renter->name }}</dd></div>
                    <div><dt class="font-medium text-zinc-500">Email pemilik</dt><dd class="mt-1 break-all text-zinc-950">{{ $booking->item->user->email }}</dd></div>
                    <div><dt class="font-medium text-zinc-500">Email penyewa</dt><dd class="mt-1 break-all text-zinc-950">{{ $booking->renter->email }}</dd></div>
                </dl>
            </div>

            <div class="card card-pad">
                <h2 class="text-lg font-bold text-zinc-950">Rincian Booking</h2>
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="font-medium text-zinc-500">Tanggal mulai</dt><dd class="mt-1 text-zinc-950">{{ $booking->start_date->format('d M Y') }}</dd></div>
                    <div><dt class="font-medium text-zinc-500">Tanggal Pengembalian</dt><dd class="mt-1 text-zinc-950">{{ $booking->end_date->format('d M Y') }}</dd></div>
                    <div><dt class="font-medium text-zinc-500">Durasi</dt><dd class="mt-1 text-zinc-950">{{ $booking->rental_days }} hari</dd></div>
                    <div><dt class="font-medium text-zinc-500">Jumlah</dt><dd class="mt-1 text-zinc-950">{{ $booking->quantity }} unit</dd></div>
                    <div><dt class="font-medium text-zinc-500">Subtotal</dt><dd class="mt-1 text-zinc-950"><x-price :amount="$booking->subtotal" suffix="" /></dd></div>
                    <div><dt class="font-medium text-zinc-500">Uang muka (DP)</dt><dd class="mt-1 text-zinc-950"><x-price :amount="$booking->deposit_amount" suffix="" /></dd></div>
                    <div><dt class="font-medium text-zinc-500">Total</dt><dd class="mt-1 font-bold text-blue-700"><x-price :amount="$booking->total_amount" suffix="" /></dd></div>
                    <div><dt class="font-medium text-zinc-500">Ongkir</dt><dd class="mt-1"><x-price :amount="$booking->delivery_fee" suffix="" /></dd></div>
                    <div><dt class="font-medium text-zinc-500">Status</dt><dd class="mt-1"><x-booking-status-badge :status="$booking->status" :booking="$booking" /></dd></div>
                </dl>
                @include('bookings.partials.fulfillment', ['booking' => $booking, 'showPickup' => true])
            </div>
        </div>
        @include('bookings.partials.payment', ['booking' => $booking, 'viewer' => 'admin'])
    </section>
@endsection
