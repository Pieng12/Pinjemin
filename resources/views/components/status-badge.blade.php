@props(['item'])

<span {{ $attributes->merge(['class' => 'badge '.($item->is_active && $item->status === \App\ItemStatus::Available ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-600')]) }}>
    {{ $item->statusLabel() }}
</span>
