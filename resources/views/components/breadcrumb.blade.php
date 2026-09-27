@props(['items' => []])

@if ($items)
    <nav {{ $attributes->merge(['class' => 'mb-5 flex flex-wrap items-center gap-2 text-sm text-zinc-500']) }} aria-label="Breadcrumb">
        @foreach ($items as $label => $url)
            @if (! $loop->first)
                <span>/</span>
            @endif
            @if ($url)
                <a href="{{ $url }}" class="font-medium hover:text-blue-700">{{ $label }}</a>
            @else
                <span class="font-medium text-zinc-800">{{ $label }}</span>
            @endif
        @endforeach
    </nav>
@endif
