@extends('chat.workspace')
@section('title', ($booking ? 'Chat Pemesanan' : 'Hubungi Pemilik').' - Pinjemin')

@section('chat_panel')
    @php
        $back = $booking
            ? route(auth()->id() === $booking->renter_user_id ? 'my-bookings.show' : 'incoming-bookings.show', $booking)
            : route('items.show', $item);
        $action = $booking ? route('chat.bookings.store', $booking) : route('chat.items.store', $item);
    @endphp
    <article class="flex min-h-0 min-w-0 flex-col bg-brand-50/45">
        <header class="flex shrink-0 items-center gap-3 border-b border-zinc-200 bg-white px-3 py-2.5 sm:px-5">
            <a href="{{ $back }}" class="btn btn-ghost size-11 px-0" aria-label="Kembali"><i data-lucide="arrow-left" class="size-5" aria-hidden="true"></i></a>
            <x-user-avatar :user="$other" size="size-10" aria-hidden="true" />
            <div class="min-w-0">
                <h1 class="truncate font-semibold" title="{{ $other->name }}">{{ $other->name }}</h1>
                <p class="text-xs text-zinc-600">{{ auth()->id() === $item->user_id ? 'Penyewa' : 'Pemilik barang' }}</p>
            </div>
        </header>
        @include('chat.item-context', ['chatItem' => $item])
        <div class="grid min-h-0 flex-1 place-items-center overflow-y-auto px-4 py-4 text-center">
            <div>
                <h2 class="text-lg font-semibold text-brand-800">{{ $booking ? 'Chat Pemesanan' : 'Hubungi Pemilik' }}</h2>
                <p class="mt-1 text-sm text-zinc-600">{{ $block['blocked'] ? 'Pengiriman pesan antara kedua akun tidak tersedia.' : 'Kirim pesan pertama untuk membuka percakapan.' }}</p>
            </div>
        </div>
        <div class="shrink-0 border-t border-zinc-200 bg-white p-3 sm:px-5">
            <p class="mb-2 text-xs leading-5 text-zinc-600">Gunakan halaman pemesanan untuk pembayaran dan unggahan dokumen.</p>
            @if($block['blocked'])
                <a href="{{ route('chat.index') }}" class="btn btn-outline">Daftar Chat</a>
            @else
                @include('chat.form', ['action' => $action, 'readOnly' => false, 'chatItem' => $item, 'showReplies' => true])
            @endif
        </div>
    </article>
@endsection
