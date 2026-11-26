<section class="shrink-0 border-b border-brand-100 bg-white px-3 py-2 sm:px-5" aria-label="Barang yang dibahas" data-chat-item-context>
    <div class="flex min-w-0 items-center gap-2.5 rounded-lg border border-brand-100 bg-brand-50/50 p-2 sm:gap-3">
        @if($chatItem)
            <x-item-image :item="$chatItem" class="h-14 max-w-14 shrink-0 [&_[data-photo-fallback]]:px-1 [&_[data-photo-fallback]]:text-[10px] [&_[data-photo-fallback]]:leading-3" />
            <div class="min-w-0 flex-1">
                <p class="text-xs text-zinc-600">Barang yang dibahas</p>
                <p class="truncate text-sm font-semibold text-zinc-950" title="{{ $chatItem->name }}">{{ $chatItem->name }}</p>
                <div class="flex flex-wrap items-baseline gap-x-2 text-xs">
                    <x-price :amount="$chatItem->daily_price" class="font-semibold text-brand-700" />
                    @if(filled($chatItem->city))<span class="truncate text-zinc-600">{{ $chatItem->city }}</span>@endif
                </div>
            </div>
            @if(($chatItem->is_active && $chatItem->status === \App\ItemStatus::Available) || $chatItem->user_id === auth()->id())
                <a href="{{ route('items.show', $chatItem) }}" class="inline-flex min-h-11 shrink-0 items-center rounded-md border border-brand-200 bg-white px-2 text-xs font-semibold text-brand-700 hover:bg-brand-50 sm:px-3" aria-label="Lihat barang {{ $chatItem->name }}">Lihat<span class="hidden sm:inline">&nbsp;barang</span></a>
            @endif
        @else
            <span class="grid size-11 shrink-0 place-items-center rounded-md bg-brand-100 text-brand-700" aria-hidden="true"><i data-lucide="package" class="size-5"></i></span>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-zinc-950" title="{{ $itemName }}">{{ $itemName }}</p>
                <p class="text-xs text-zinc-600">Barang sudah dihapus dari katalog.</p>
            </div>
        @endif
    </div>
</section>
