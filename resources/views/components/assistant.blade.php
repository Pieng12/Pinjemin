@props(['context' => null])
@php
    $assistantUser = auth()->user();
    $assistantEnabled = (bool) config('features.marketplace_enabled');
    $assistantChatEnabled = $assistantEnabled && config('features.chat_enabled');
    $assistantAdmin = $assistantUser?->isAdmin() ?? false;
    $assistantContext = ! $assistantEnabled ? 'promotion' : (! $assistantUser ? 'guest' : ($context ?? (request()->routeIs('admin.*') ? 'admin' : (request()->routeIs('owner.*', 'my-items.*', 'incoming-bookings.*', 'payment-methods.*') ? 'owner' : 'renter'))));
    $assistantDestinations = collect(config('assistant.destinations'))->filter(function ($destination) use ($assistantEnabled, $assistantChatEnabled, $assistantAdmin, $assistantUser) {
        return ($assistantEnabled || ($destination['public'] ?? false))
            && (! ($destination['chat'] ?? false) || $assistantChatEnabled)
            && (! ($destination['admin'] ?? false) || $assistantAdmin)
            && (! ($destination['guest'] ?? false) || ! $assistantUser);
    })->map(function ($destination) use ($assistantUser) {
        $needsLogin = ($destination['auth'] ?? false) && ! $assistantUser;
        $destination['url'] = isset($destination['external'])
            ? ($destination['external'] === 'email' ? 'mailto:'.config('assistant.email') : config('assistant.instagram'))
            : route($needsLogin ? 'login' : $destination['route']).(! $needsLogin && isset($destination['hash']) ? '#'.$destination['hash'] : '');

        return ['label' => $needsLogin ? 'Masuk untuk melanjutkan' : $destination['label'], 'url' => $destination['url']];
    });
    $assistantTopics = collect(config('assistant.topics'))->filter(fn ($topic) => ($assistantEnabled || ($topic['public'] ?? false)) && (! ($topic['admin'] ?? false) || $assistantAdmin) && (! ($topic['chat'] ?? false) || $assistantChatEnabled))
        ->map(function ($topic, $id) use ($assistantDestinations) {
            $topic['id'] = $id;
            $topic['actions'] = collect($topic['actions'])->filter(fn ($action) => $assistantDestinations->has($action))->map(fn ($action) => $assistantDestinations->get($action))->unique('url')->values()->all();

            return $topic;
        });
    $assistantInitial = array_values(array_intersect(config('assistant.initial_topics.'.$assistantContext, []), $assistantTopics->keys()->all()));
    $assistantData = [
        'version' => config('assistant.version'),
        'scope' => $assistantUser ? hash('sha256', 'assistant|'.$assistantUser->getAuthIdentifier()) : 'guest',
        'context' => $assistantContext,
        'page' => \Illuminate\Support\Facades\Route::currentRouteName(),
        'marketplaceEnabled' => $assistantEnabled,
        'chatEnabled' => $assistantChatEnabled,
        'navigation' => $assistantDestinations->all(),
        'topics' => $assistantTopics->values()->all(),
        'initial' => $assistantInitial,
        'contacts' => $assistantDestinations->only(['contact', 'instagram'])->values()->all(),
        'logoutUrl' => route('logout'),
    ];
@endphp

<div {{ $attributes->class(['pinjemin-assistant']) }} data-assistant>
    <script type="application/json" data-assistant-config>{!! \Illuminate\Support\Js::encode($assistantData) !!}</script>
    <div class="assistant-launch-area" data-assistant-launch-area hidden>
        <div class="assistant-greeting" data-assistant-greeting hidden>
            <span>Informasi dan panduan layanan</span>
            <button type="button" data-assistant-dismiss-greeting aria-label="Tutup sapaan bantuan"><i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i></button>
        </div>
        <button type="button" class="assistant-launcher" data-assistant-launcher aria-label="Buka Pusat Bantuan Pinjemin" aria-expanded="false" aria-controls="pinjemin-assistant-dialog" aria-haspopup="dialog">
            <img src="{{ asset('images/maskot-pinjemin-assistant.webp') }}" alt="" width="72" height="97" data-assistant-mascot>
            <span class="assistant-launcher-label">Bantuan</span>
        </button>
    </div>
    <dialog id="pinjemin-assistant-dialog" class="assistant-dialog" data-assistant-dialog aria-labelledby="assistant-title" aria-describedby="assistant-description">
        <header class="assistant-header">
            <div class="assistant-avatar"><img src="{{ asset('images/maskot-pinjemin-assistant.webp') }}" alt="" width="36" height="48" data-assistant-mascot></div>
            <div class="min-w-0 flex-1"><h2 id="assistant-title" class="text-base font-semibold">Pusat Bantuan Pinjemin</h2><p class="mt-0.5 text-xs text-white/80">Informasi layanan otomatis</p></div>
            <button type="button" class="assistant-icon-button" data-assistant-close aria-label="Tutup Pusat Bantuan Pinjemin"><i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i></button>
        </header>
        <p id="assistant-description" class="sr-only">Pilih panduan sesuai tujuan Anda atau tanyakan informasi Pinjemin. Informasi diberikan secara otomatis. Untuk menghubungi administrator, gunakan kontak yang tersedia.</p>
        <div class="assistant-tabs" role="tablist" aria-label="Jenis bantuan">
            <button id="assistant-guides-tab" type="button" role="tab" aria-selected="true" aria-controls="assistant-guides-panel" data-assistant-tab="guides">Panduan</button>
            <button id="assistant-chat-tab" type="button" role="tab" aria-selected="false" aria-controls="assistant-chat-panel" tabindex="-1" data-assistant-tab="chat">Informasi Layanan</button>
        </div>
        <section id="assistant-guides-panel" class="assistant-guides" role="tabpanel" aria-labelledby="assistant-guides-tab" data-assistant-guides>
            <div data-guide-picker>
                <div class="assistant-guide-intro"><p class="assistant-guide-eyebrow">Panduan penggunaan</p><h3>Pilih Panduan Layanan</h3><p>Pilih layanan untuk melihat prosedur dan panduan penggunaannya.</p></div>
                <div class="assistant-guide-list" data-guide-list></div>
                <button type="button" data-assistant-tutorial aria-haspopup="dialog" aria-controls="pinjemin-tutorial" class="assistant-guide-page"><span><strong>Panduan Halaman Ini</strong><small>Lihat fungsi tombol pada halaman yang sedang dibuka.</small></span><span class="assistant-guide-page-action">Jalankan Tutorial <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i></span></button>
            </div>
            <div data-guide-detail hidden>
                <button type="button" class="assistant-guide-back" data-guide-back><i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i> Semua panduan</button>
                <p class="assistant-guide-eyebrow mt-3">Panduan bertahap</p>
                <h3 class="assistant-guide-title" data-guide-title></h3>
                <p class="assistant-guide-description" data-guide-description></p>
                <ol class="assistant-guide-roadmap" data-guide-roadmap aria-label="Urutan panduan"></ol>
                <p class="assistant-guide-instruction" data-guide-instruction></p>
                <div class="assistant-guide-actions"><button type="button" class="btn btn-primary w-full" data-guide-start></button><button type="button" class="btn btn-outline w-full" data-guide-use-page hidden></button><button type="button" class="assistant-text-button justify-center" data-guide-end hidden>Akhiri panduan</button></div>
            </div>
            <p class="assistant-guide-feedback" role="status" data-guide-feedback hidden></p>
        </section>
        <div id="assistant-chat-panel" class="assistant-chat" role="tabpanel" aria-labelledby="assistant-chat-tab" data-assistant-chat hidden>
        <div class="assistant-conversation" data-assistant-conversation tabindex="0" role="region" aria-label="Riwayat percakapan"></div>
        <button type="button" class="assistant-new-message" data-assistant-new-message hidden>Lihat jawaban baru ↓</button>
        <div class="assistant-topic-browser" data-assistant-topics hidden>
            <div class="flex items-center justify-between gap-2"><h3 class="text-sm font-semibold">Semua topik bantuan</h3><button type="button" class="assistant-text-button" data-assistant-hide-topics>Kembali</button></div>
            <div class="assistant-topic-list" data-assistant-topic-list></div>
        </div>
        <div class="assistant-tools">
            <button type="button" class="assistant-text-button" data-assistant-show-topics>Semua topik</button>
            <button type="button" class="assistant-text-button" data-assistant-reset>Mulai ulang</button>
        </div>
        <form class="assistant-compose" data-assistant-form>
            <label for="assistant-question" class="sr-only">Pertanyaan tentang Pinjemin</label>
            <div class="assistant-input-row"><input id="assistant-question" type="text" maxlength="300" autocomplete="off" placeholder="Tanyakan tentang Pinjemin…" enterkeyhint="send" data-assistant-input aria-describedby="assistant-input-help assistant-input-error"><button type="submit" class="assistant-send" aria-label="Kirim pertanyaan"><i data-lucide="arrow-right" class="h-5 w-5" aria-hidden="true"></i></button></div>
            <p id="assistant-input-error" class="mt-1 text-xs text-red-700" data-assistant-error hidden></p>
            <p id="assistant-input-help" class="mt-2 text-xs leading-5 text-zinc-500">Jangan kirim kata sandi, identitas, atau data transaksi pribadi.</p>
        </form>
        </div>
        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-assistant-status></p>
    </dialog>
    <x-tutorial />
    <noscript>
        <details class="mx-auto my-6 w-full max-w-3xl rounded-lg border border-blue-100 bg-white p-5 text-sm leading-6">
            <summary class="min-h-11 cursor-pointer font-semibold text-blue-700">Bantuan Pinjemin</summary>
            <div class="grid gap-5 pt-4">
                @foreach ($assistantTopics->only($assistantInitial) as $topic)
                    <section><h3 class="font-semibold">{{ $topic['question'] }}</h3><p class="mt-1 whitespace-pre-line text-zinc-600">{{ $topic['answer'] }}</p></section>
                @endforeach
                <div class="flex flex-wrap gap-4"><a href="{{ route('legal.terms') }}" class="font-semibold text-blue-700">Syarat &amp; Ketentuan</a><a href="{{ route('legal.privacy') }}" class="font-semibold text-blue-700">Kebijakan Privasi</a><a href="mailto:{{ config('assistant.email') }}" class="font-semibold text-blue-700">Email Pinjemin</a><a href="{{ config('assistant.instagram') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-blue-700">Instagram Pinjemin</a></div>
            </div>
        </details>
    </noscript>
</div>
