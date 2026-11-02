@props([
    'name',
    'label',
    'value' => null,
    'required' => false,
    'help' => null,
])

@php
    $rawValue = old($name, $value);
    $rawValue = $rawValue !== null ? (string) $rawValue : '';
    $inputId = $attributes->get('id') ?? $name;
@endphp

<div>
    <label for="{{ $inputId }}" class="field-label">
        {{ $label }} @if ($required)<span class="text-red-600">*</span>@endif
    </label>
    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="number"
        min="0"
        step="0.01"
        inputmode="decimal"
        autocomplete="off"
        data-currency-input
        value="{{ $rawValue }}"
        @required($required)
        {{ $attributes->except(['id', 'name', 'value'])->merge(['class' => 'input']) }}
    >
    @if ($help)
        <p class="field-help">{{ $help }}</p>
    @endif
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>
