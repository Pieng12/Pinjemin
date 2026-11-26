<ul class="grid gap-2" aria-label="Lampiran pesan">
    @foreach($message->attachments as $index => $attachment)
        @php($url = route('chat.attachments.show', [$conversation, $message, $index]))
        <li class="rounded-lg border border-brand-100 bg-white p-2">
            @if(str_starts_with($attachment['mime'], 'image/'))
                <a href="{{ $url }}" target="_blank" rel="noopener" class="block rounded-md">
                    <img src="{{ $url }}" alt="{{ $attachment['name'] }}" loading="lazy" decoding="async" class="aspect-[4/3] w-56 max-w-full rounded-md bg-zinc-50 object-contain sm:w-64">
                </a>
            @endif
            <div class="flex min-w-0 flex-wrap items-center justify-between gap-x-3 gap-y-0 px-1">
                <span class="max-w-full wrap-anywhere text-xs font-medium text-zinc-700">{{ $attachment['name'] }}</span>
                <a href="{{ $url }}?download=1" class="inline-flex min-h-9 items-center text-xs font-semibold text-brand-700 hover:underline" aria-label="Unduh {{ $attachment['name'] }}">Unduh</a>
            </div>
        </li>
    @endforeach
</ul>
