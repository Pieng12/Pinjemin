@php($validationMessages = collect($errors->getBags())->flatMap(fn ($bag) => $bag->all())->unique())
@if($validationMessages->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800']) }} role="alert" tabindex="-1" data-validation-summary>
        <p class="font-semibold">Periksa kembali data yang kamu masukkan.</p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            @foreach($validationMessages as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    </div>
@endif
