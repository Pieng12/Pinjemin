@props([
    'name',
    'label',
    'value' => null,
    'required' => false,
    'help' => null,
])

@php
    $rawValue = old($name, $value);
    $rawValue = $rawValue !== null && $rawValue !== '' ? (string) (int) $rawValue : '';
    $inputId = $attributes->get('id') ?? $name;
@endphp

<div>
    <label for="{{ $inputId }}_display" class="field-label">
        {{ $label }} @if ($required)<span class="text-red-600">*</span>@endif
    </label>
    <input
        id="{{ $inputId }}_display"
        type="text"
        inputmode="numeric"
        autocomplete="off"
        data-currency-display
        data-target="{{ $inputId }}"
        value="{{ $rawValue }}"
        {{ $attributes->except(['id', 'name', 'value'])->merge(['class' => 'input']) }}
    >
    <input id="{{ $inputId }}" type="hidden" name="{{ $name }}" value="{{ $rawValue }}" data-currency-raw @required($required)>
    @if ($help)
        <p class="field-help">{{ $help }}</p>
    @endif
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>
