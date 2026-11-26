@extends('layouts.app')
@section('chat_layout', 'true')

@section('content')
    @php
        $inboxSearch = $inboxSearch ?? '';
        $inboxOnly = request()->routeIs('chat.index');
        $inboxUrl = $conversations->url($conversations->currentPage());
    @endphp
    <section class="mx-auto grid h-[calc(100dvh-4.5rem)] min-h-96 w-full max-w-[1440px] grid-cols-1 grid-rows-[minmax(0,1fr)] overflow-hidden bg-white md:my-4 md:h-[calc(100dvh-6.5rem)] md:w-[calc(100%-2rem)] md:grid-cols-[minmax(265px,320px)_minmax(0,1fr)] md:rounded-lg md:border md:border-zinc-200 xl:grid-cols-[360px_minmax(0,1fr)]">
        <aside class="{{ $inboxOnly ? 'flex' : 'hidden md:flex' }} min-h-0 min-w-0 flex-col border-zinc-200 md:border-r" aria-label="Daftar percakapan">
            <header class="border-b border-zinc-200 px-4 py-4 sm:px-5">
                <div class="flex items-center justify-between gap-2">
                    @if($inboxOnly)
                        <h1 class="text-xl font-semibold tracking-tight text-brand-800 sm:text-2xl">Chat Barang</h1>
                    @else
                        <h2 class="text-2xl font-semibold tracking-tight text-brand-800">Chat</h2>
                    @endif
                    <x-button :href="route('browse')" variant="outline" class="whitespace-nowrap">Jelajahi Barang</x-button>
                </div>
                <form method="GET" action="{{ route('chat.index') }}" class="mt-3 flex items-center gap-2">
                    <label for="search-conversations" class="sr-only">Cari percakapan</label>
                    <input id="search-conversations" type="search" name="q" value="{{ $inboxSearch }}" maxlength="100" class="input mt-0 min-w-0 flex-1" placeholder="Cari berdasarkan nama barang">
                    <button type="submit" class="btn btn-outline size-11 px-0" aria-label="Cari percakapan" title="Cari percakapan"><i data-lucide="search" class="size-4" aria-hidden="true"></i></button>
                </form>
                @if($inboxSearch !== '')
                    <a href="{{ route('chat.index') }}" class="mt-2 inline-block text-sm font-semibold text-brand-700 underline underline-offset-2">Hapus pencarian</a>
                @endif
            </header>
            <div class="min-h-0 flex-1 overflow-y-auto" data-chat-inbox="{{ $inboxUrl }}" @isset($conversation) data-selected-chat="{{ $conversation->id }}" @endisset>
                @include('chat.inbox', ['selected' => $conversation->id ?? null])
            </div>
            <div class="border-t border-zinc-100 px-4 sm:px-5">
                <a href="{{ $inboxUrl }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-700 hover:underline">Muat ulang daftar</a>
            </div>
        </aside>

        @yield('chat_panel')
    </section>
@endsection
