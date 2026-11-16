@props(['bag' => null, 'formId' => null, 'fieldIds' => []])

@php
    $errorBags = $bag !== null ? [$bag => $errors->getBag($bag)] : $errors->getBags();
    $submittedFormId = old('_ui_form');
    $submittedFormId = is_string($submittedFormId) ? $submittedFormId : null;
    $formId ??= $submittedFormId;
@endphp
@if(collect($errorBags)->contains(fn ($messages) => $messages->any()))
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800']) }} role="alert" tabindex="-1" data-validation-summary @if($formId) data-form-id="{{ $formId }}" @endif>
        <p class="font-semibold">Periksa kembali data yang Anda masukkan.</p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            @foreach($errorBags as $bagName => $messages)
                @foreach($messages->messages() as $field => $fieldMessages)
                    @php
                        $errorFormId = $formId;
                        $fieldId = $fieldIds[$field] ?? $fieldIds[preg_replace('/\.[0-9]+(?:\..*)?$/', '', $field)] ?? null;
                        if ($fieldId === null && preg_match('/^method-(?:new|[0-9]+)$/', $bagName)) {
                            $fieldId = $bagName.'-'.$field;
                        }
                        if ($fieldId === null && $formId !== null) {
                            $fieldId = $formId.'-'.preg_replace('/\.[0-9]+(?:\..*)?$/', '', $field);
                        }
                    @endphp
                    @foreach($fieldMessages as $message)
                        <li data-error-field="{{ $field }}" data-error-bag="{{ $bagName }}" @if($errorFormId) data-form-id="{{ $errorFormId }}" @endif>
                            @if($fieldId)<a href="#{{ $fieldId }}" class="underline underline-offset-2" data-error-link>{{ $message }}</a>@else<span data-error-text>{{ $message }}</span>@endif
                        </li>
                    @endforeach
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
