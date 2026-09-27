@props(['title', 'description', 'actionLabel' => null, 'actionUrl' => null, 'showMark' => true, 'framed' => true])

<div {{ $attributes->merge(['class' => $framed ? 'rounded-lg border border-dashed border-zinc-300 bg-white p-8 text-center shadow-sm' : 'py-8 text-center']) }}>
    @if ($showMark)
        <img src="{{ asset('images/landing/logo-pinjemin.png') }}" alt="" class="mx-auto mb-4 h-12 w-12 object-contain" width="48" height="43">
    @endif
    <h2 class="text-lg font-bold text-zinc-950">{{ $title }}</h2>
    <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-zinc-600">{{ $description }}</p>
    @if ($actionLabel && $actionUrl)
        <x-button :href="$actionUrl" class="mt-5">{{ $actionLabel }}</x-button>
    @endif
</div>
