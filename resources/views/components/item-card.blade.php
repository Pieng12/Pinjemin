@props(['item', 'compact' => false])

<article {{ $attributes->merge(['class' => 'catalog-card group min-w-0 rounded-lg border border-zinc-200 bg-white p-2 shadow-sm transition duration-300 hover:border-blue-200 hover:shadow-md sm:p-3']) }}>
    <a href="{{ route('items.show', $item) }}" class="block h-full">
        <div class="overflow-hidden rounded-lg">
            <x-item-image :item="$item" fit="contain" class="aspect-[4/3]" />
        </div>
        <div class="mt-3 px-1 pb-1">
            <p class="truncate text-xs text-zinc-500">{{ $item->category->name }}</p>
            <h2 class="mt-1 line-clamp-2 min-h-10 text-sm font-semibold leading-5 text-zinc-950 sm:min-h-12 sm:text-base sm:leading-6">{{ $item->name }}</h2>
            <p class="mt-1 truncate text-xs text-zinc-500 sm:text-sm">{{ $item->city }}</p>
            <div class="mt-4 flex flex-wrap items-baseline gap-x-1 border-t border-zinc-100 pt-3 text-sm">
                <x-price :amount="$item->daily_price" suffix="" class="font-semibold text-blue-700" />
                <span class="text-xs text-zinc-500">/ hari</span>
            </div>
        </div>
    </a>
</article>
