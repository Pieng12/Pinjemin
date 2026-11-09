@props(['condition'])

@php($colors = $condition === \App\ItemCondition::Fair ? 'bg-amber-50 text-amber-800' : 'bg-brand-50 text-brand-700')
<span {{ $attributes->merge(['class' => 'badge '.$colors]) }}>
    {{ $condition->label() }}
</span>
