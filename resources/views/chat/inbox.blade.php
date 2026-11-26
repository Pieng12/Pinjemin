@php
    $inboxSearch = $inboxSearch ?? '';
@endphp
<ul class="divide-y divide-zinc-100">
    @forelse($conversations as $entry)
        @php
            $other = $entry->otherParticipant(auth()->user());
            $active = (int) ($selected ?? 0) === $entry->id;
            $time = $entry->latestMessage?->created_at->copy()->timezone('Asia/Jakarta');
        @endphp
        <li>
            <a href="{{ route('chat.show', ['conversation' => $entry, 'page' => $conversations->currentPage() > 1 ? $conversations->currentPage() : null, 'q' => $inboxSearch !== '' ? $inboxSearch : null]) }}" class="flex min-w-0 items-center gap-3 border-s-2 px-4 py-3 hover:bg-brand-50 {{ $active ? 'border-brand-700 bg-brand-50' : 'border-transparent' }}" @if($active) aria-current="page" @endif>
                <x-user-avatar :user="$other" size="size-11" aria-hidden="true" />
                <div class="min-w-0 flex-1">
                    <div class="flex min-w-0 items-baseline justify-between gap-2">
                        <p class="truncate text-sm font-semibold text-zinc-950">{{ $other->name }}</p>
                        @if($time)<time class="shrink-0 text-xs text-zinc-600" datetime="{{ $time->toIso8601String() }}" title="{{ $time->translatedFormat('d M Y H:i') }} WIB">{{ $time->isToday() ? $time->format('H:i') : $time->translatedFormat('d M') }}</time>@endif
                    </div>
                    <p class="truncate text-xs font-medium text-brand-700">{{ $entry->direct_key === null ? $entry->item_name : 'Riwayat chat lama' }}</p>
                    @if($entry->latestMessage)
                        <div class="mt-1 flex min-w-0 items-center gap-2">
                            <p class="min-w-0 flex-1 truncate text-sm {{ $entry->unread_exists ? 'font-semibold text-brand-800' : 'text-zinc-600' }}">@if($entry->latestMessage->sender_id === auth()->id())Anda: @endif{{ $entry->latestMessage->preview() }}</p>
                            @if($entry->unread_exists)<span class="size-2.5 shrink-0 rounded-full bg-brand-700"><span class="sr-only">Belum dibaca</span></span>@endif
                        </div>
                    @endif
                </div>
            </a>
        </li>
    @empty
        <li class="px-5 py-7 text-sm leading-6 text-zinc-600">{{ $inboxSearch !== '' ? 'Tidak ada percakapan untuk barang yang dicari.' : 'Belum ada percakapan. Pilih barang di katalog, lalu tekan Hubungi Pemilik untuk mulai.' }}</li>
    @endforelse
</ul>
@if($conversations->hasPages())
    <div class="p-4">{{ $conversations->onEachSide(0)->links('pagination::simple-tailwind') }}</div>
@endif
