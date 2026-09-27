@props([
    'href' => null,
    'variant' => 'primary',
    'type' => 'button',
])

@php
    $classes = match ($variant) {
        'secondary' => 'btn btn-secondary',
        'outline' => 'btn btn-outline',
        'ghost' => 'btn btn-ghost',
        'danger' => 'btn btn-danger',
        default => 'btn btn-primary',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        <span data-submit-label>{{ $slot }}</span>
        <span data-submit-loading class="hidden items-center gap-2">
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
            Memproses...
        </span>
    </button>
@endif
