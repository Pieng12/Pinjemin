@props(['item', 'class' => 'h-44', 'fit' => 'contain'])

@php($photo = $item->relationLoaded('primaryPhoto') ? $item->primaryPhoto : null)
@php($photo = $photo ?: ($item->relationLoaded('photos') ? $item->photos->first() : null))
@php($photo = $photo?->path === 'items/demo/pinjemin-placeholder.png' ? null : $photo)

<div {{ $attributes->merge(['class' => $class.' relative flex w-full items-center justify-center overflow-hidden rounded-lg bg-zinc-50']) }} data-item-image>
    @if ($photo)
        <img src="{{ $photo->url() }}" alt="{{ $item->name }}" loading="lazy" decoding="async" data-item-photo class="h-full w-full {{ $fit === 'contain' ? 'object-contain' : 'object-cover' }} transition duration-300">
    @endif
    <span data-photo-fallback @if ($photo) hidden @endif class="px-3 text-center text-xs font-medium leading-4 text-zinc-500">Foto belum tersedia</span>
</div>
