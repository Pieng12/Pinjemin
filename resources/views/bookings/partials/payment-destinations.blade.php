<div class="grid gap-3 sm:grid-cols-2" data-payment-destinations>
    @foreach($methods as $index => $method)
        <div class="rounded-md border border-zinc-200 bg-white p-4 text-sm" data-payment-destination="{{ $index }}">
            <p class="font-semibold text-zinc-950">{{ $method['label'] }}</p>
            <p class="mt-1 text-zinc-600">{{ $method['account_name'] }}</p>
            @if($method['account_identifier'] ?? null)
                <p class="mt-2 wrap-anywhere font-mono text-zinc-950" data-copy-source>{{ $method['account_identifier'] }}</p>
                <button type="button" class="mt-2 min-h-11 text-sm font-semibold text-brand-700" data-copy-value="{{ $method['account_identifier'] }}" hidden>Salin nomor</button>
            @endif
            @if($method['qris_path'] ?? null)
                <img src="{{ route('bookings.payment-method-image', [$booking, $index]) }}" alt="QRIS {{ $method['account_name'] }}" class="mt-3 max-h-56 w-full rounded-md border border-zinc-200 bg-white object-contain p-2">
            @endif
            @if($method['instructions'] ?? null)<p class="mt-3 whitespace-pre-line leading-6 text-zinc-600">{{ $method['instructions'] }}</p>@endif
        </div>
    @endforeach
</div>
