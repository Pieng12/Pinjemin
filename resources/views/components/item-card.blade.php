@props(['item', 'compact' => false, 'period' => []])

<article {{ $attributes->merge(['class' => 'catalog-card flex min-w-0 flex-col rounded-lg border border-zinc-200 bg-white shadow-sm transition-colors hover:border-brand-300 focus-within:border-brand-300']) }}>
    <a href="{{ route('items.show', ['item' => $item, ...$period]) }}" class="group/detail flex flex-1 flex-col rounded-t-lg">
        <div class="mx-2 mt-2 overflow-hidden rounded-md sm:mx-3 sm:mt-3">
            <x-item-image :item="$item" fit="contain" class="aspect-[4/3] motion-safe:group-hover/detail:[&>img]:scale-[1.025] motion-reduce:[&>img]:transition-none" />
        </div>
        <div class="flex flex-1 flex-col p-2.5 sm:p-3.5">
            <p class="truncate text-xs font-medium text-brand-700">{{ $item->category->name }}</p>
            <h2 class="mt-1 line-clamp-2 min-h-10 text-sm font-semibold leading-5 text-zinc-950 sm:min-h-12 sm:text-base sm:leading-6">{{ $item->name }}</h2>
            <p class="mt-1 break-words text-xs leading-5 text-zinc-600 sm:text-sm">{{ $item->city }} <span aria-hidden="true">·</span> {{ $item->conditionLabel() }}</p>
            <div class="mt-auto pt-2">
                <div class="flex flex-wrap items-baseline gap-x-1 text-sm sm:text-base">
                    <x-price :amount="$item->daily_price" suffix="" class="font-semibold text-brand-700" />
                    <span class="text-xs text-zinc-500">/ hari</span>
                </div>
            </div>
        </div>
    </a>
    @if(config('features.chat_enabled') && auth()->id() === $item->user_id)
        <a href="{{ route('my-items.show', $item) }}" class="flex min-h-11 items-center justify-center rounded-b-lg border-t border-brand-100 bg-brand-50/60 px-2 text-center text-xs font-semibold text-brand-700 transition-colors hover:bg-brand-50 sm:text-sm">Kelola Barang</a>
    @endif
</article>
