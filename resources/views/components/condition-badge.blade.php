@props(['condition'])

<span {{ $attributes->merge(['class' => 'badge bg-amber-50 text-amber-800']) }}>
    {{ $condition->label() }}
</span>
