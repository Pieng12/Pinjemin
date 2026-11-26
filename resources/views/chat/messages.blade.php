@foreach($messages as $message)
    @php($mine = $message->sender_id === auth()->id())
    <li data-chat-message="{{ $message->id }}" class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
        <article class="min-w-0 max-w-[90%] rounded-xl border px-3 py-2 text-sm sm:max-w-[78%] {{ $mine ? 'rounded-tr-sm border-brand-200 bg-brand-100 text-brand-800' : 'rounded-tl-sm border-zinc-200 bg-white text-zinc-900' }}">
            @if($message->attachments)
                @include('chat.attachments')
            @endif
            @if($message->body !== '')
                <p class="{{ $message->attachments ? 'mt-2' : '' }} whitespace-pre-wrap wrap-anywhere leading-6">{{ $message->body }}</p>
            @endif
            <div class="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-zinc-600">
                <span class="sr-only">{{ $mine ? 'Dikirim oleh Anda' : 'Diterima' }}</span>
                <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->copy()->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }} WIB</time>
                @unless($mine)
                    <a href="{{ route('chat.reports.create', [$conversation, $message]) }}" class="inline-flex min-h-8 items-center font-medium text-brand-700 hover:underline">Laporkan Pesan</a>
                @endunless
            </div>
        </article>
    </li>
@endforeach
