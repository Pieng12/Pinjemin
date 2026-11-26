@props(['title', 'description' => null])

<header {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-x-6 gap-y-4']) }}>
    <div class="min-w-0">
        <h1 class="font-semibold wrap-anywhere text-zinc-950">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-zinc-600">{{ $description }}</p>
        @endif
    </div>
    @unless ($slot->isEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endunless
</header>
