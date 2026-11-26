@extends('chat.workspace')
@section('title', 'Percakapan - Pinjemin')
@section('chat_panel')
        <article class="flex min-h-0 min-w-0 flex-col bg-brand-50/45" data-chat-thread
            data-updates-url="{{ route('chat.updates', $conversation) }}" data-history="{{ $before ? 'true' : 'false' }}"
            data-read-id="{{ $conversation->getAttribute($conversation->readColumn(auth()->user())) }}">
            <header class="flex shrink-0 items-center gap-2.5 border-b border-zinc-200 bg-white px-3 py-2.5 sm:gap-3 sm:px-5">
                <div class="md:hidden"><a href="{{ $conversations->url($conversations->currentPage()) }}" class="btn btn-ghost size-11 px-0" aria-label="Kembali ke daftar chat"><i data-lucide="arrow-left" class="size-5" aria-hidden="true"></i></a></div>
                <x-user-avatar :user="$other" size="size-10" aria-hidden="true" />
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-base font-semibold text-zinc-950 sm:text-lg" title="{{ $other->name }}">{{ $other->name }}</h1>
                    <p class="text-xs text-zinc-600">{{ $conversation->direct_key !== null ? 'Riwayat chat lama' : (auth()->id() === $conversation->owner_id ? 'Penyewa' : 'Pemilik barang') }}</p>
                </div>
                <details class="relative shrink-0 text-sm">
                    <summary class="btn btn-ghost size-11 cursor-pointer list-none px-0 [&::-webkit-details-marker]:hidden" aria-label="Opsi Percakapan" title="Opsi Percakapan"><span aria-hidden="true" class="text-xl leading-none">⋮</span></summary>
                    <form method="POST" action="{{ route($block['blocked_by_me'] ? 'chat.unblock' : 'chat.block', $conversation) }}" class="absolute end-0 top-full z-10 mt-1 w-56 rounded-lg border border-zinc-200 bg-white px-3 py-2 shadow-lg">
                        @csrf
                        @if($block['blocked_by_me']) @method('DELETE') @endif
                        <p class="max-w-56 py-2 text-xs leading-5 text-zinc-600">Blokir menghentikan pertukaran pesan antara kedua akun. Riwayat tetap tersedia.</p>
                        <button type="submit" class="inline-flex min-h-11 items-center font-semibold {{ $block['blocked_by_me'] ? 'text-brand-700' : 'text-red-700' }}">{{ $block['blocked_by_me'] ? 'Buka Blokir' : 'Blokir Pengguna' }}</button>
                    </form>
                </details>
            </header>
            @if($conversation->direct_key === null)
                @include('chat.item-context', ['chatItem' => $conversation->item, 'itemName' => $conversation->item_name])
            @endif
            <div class="flex shrink-0 flex-wrap justify-end gap-x-4 px-4 text-xs font-semibold text-brand-700 sm:px-5">
                @php($threadParams = ['conversation' => $conversation, 'page' => $conversations->currentPage() > 1 ? $conversations->currentPage() : null, 'q' => $inboxSearch !== '' ? $inboxSearch : null])
                @if($older)<a class="inline-flex min-h-9 items-center hover:underline" href="{{ route('chat.show', $threadParams + ['before' => $messages->first()->id]) }}">Lihat Pesan Lama</a>@endif
                <a class="inline-flex min-h-9 items-center hover:underline" href="{{ route('chat.show', $threadParams) }}">{{ $before ? 'Lihat Pesan Terbaru' : 'Muat ulang' }}</a>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-3 pb-4 sm:px-5" data-chat-scroll tabindex="0" role="region" aria-label="Riwayat pesan">
                <ol class="grid min-h-full content-end gap-3" data-chat-messages>
                    @include('chat.messages')
                </ol>
            </div>
            <p data-chat-new hidden class="px-4 pt-2 text-sm text-brand-700"><button type="button" class="min-h-11 font-semibold" data-chat-jump>Lihat pesan baru</button></p>
            <p role="status" class="sr-only" data-chat-announcement></p>
            <div class="shrink-0 border-t border-zinc-200 bg-white p-3 sm:px-5">
                <p data-chat-read-only @if(!$readOnlyReason) hidden @endif class="mb-2 rounded-lg bg-zinc-100 p-3 text-sm text-zinc-700" role="status">{{ $readOnlyReason }}</p>
                <form method="POST" action="{{ route('chat.read', $conversation) }}" data-chat-read>
                    @csrf
                    <input type="hidden" name="through" value="{{ $incomingId }}">
                    @if($incomingId > $conversation->getAttribute($conversation->readColumn(auth()->user())))
                        <noscript><button type="submit" class="btn btn-outline">Tandai Sudah Dibaca</button></noscript>
                    @endif
                </form>
                @include('chat.form', ['action' => route('chat.send', $conversation), 'readOnly' => $readOnlyReason !== null, 'chatItem' => $conversation->item])
            </div>
        </article>
@endsection
