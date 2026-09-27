@props([
    'label',
    'value',
    'description' => null,
    'href' => null,
])

@php($classes = 'soft-card card-pad flex items-start justify-between gap-4 transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md')

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        <div>
            <p class="text-sm font-medium text-zinc-500">{{ $label }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-zinc-950">{{ $value }}</p>
            @if ($description)
                <p class="mt-2 text-sm leading-6 text-zinc-600">{{ $description }}</p>
            @endif
        </div>
        @unless ($slot->isEmpty())
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                {{ $slot }}
            </div>
        @endunless
    </a>
@else
    <div {{ $attributes->merge(['class' => $classes]) }}>
        <div>
            <p class="text-sm font-medium text-zinc-500">{{ $label }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-zinc-950">{{ $value }}</p>
            @if ($description)
                <p class="mt-2 text-sm leading-6 text-zinc-600">{{ $description }}</p>
            @endif
        </div>
        @unless ($slot->isEmpty())
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                {{ $slot }}
            </div>
        @endunless
    </div>
@endif
