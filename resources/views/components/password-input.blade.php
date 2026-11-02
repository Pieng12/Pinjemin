@props(['name' => 'password', 'label' => 'Password', 'autocomplete' => 'current-password', 'help' => null])
@php($inputId = $attributes->get('id', $name))
<div data-password-field>
    <label for="{{ $inputId }}" class="field-label">{{ $label }}</label>
    <div class="relative">
        <input {{ $attributes->except(['value', 'id', 'type'])->merge(['class' => 'input pr-14']) }} id="{{ $inputId }}" name="{{ $name }}" type="password" autocomplete="{{ $autocomplete }}" required @if($help) aria-describedby="{{ $inputId }}-help" @endif @error($name) aria-invalid="true" @enderror>
        <button type="button" data-password-toggle aria-controls="{{ $inputId }}" aria-pressed="false" aria-label="Tampilkan {{ strtolower($label) }}" title="Tampilkan {{ strtolower($label) }}" class="absolute right-0 top-0 hidden h-11 w-11 items-center justify-center rounded-md text-zinc-500 hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-blue-700">
            <i data-lucide="eye" data-password-show class="h-5 w-5" aria-hidden="true"></i>
            <i data-lucide="eye-off" data-password-hide class="h-5 w-5" aria-hidden="true" hidden></i>
        </button>
    </div>
    @if($help)<p id="{{ $inputId }}-help" class="field-help">{{ $help }}</p>@endif
    @error($name)<p class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
