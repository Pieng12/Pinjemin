@props(['title', 'intro', 'sections', 'version'])

<section class="page-shell max-w-6xl">
    <header class="max-w-3xl">
        <p class="text-sm font-semibold text-blue-700">Panduan menggunakan Pinjemin</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-zinc-950 sm:text-4xl">{{ $title }}</h1>
        <p data-legal-intro class="mt-3 text-sm leading-6 text-zinc-600 sm:mt-4 sm:text-base sm:leading-7">{{ $intro }}</p>
        <p class="mt-4 text-xs leading-6 text-zinc-500">Diperbarui {{ config('legal.published_on') }} · Versi {{ $version }}</p>
    </header>

    <nav aria-label="Dokumen aturan" class="mt-7 flex flex-wrap gap-2 border-b border-zinc-200 pb-5 text-sm font-semibold">
        <a href="{{ route('legal.terms') }}" @if(request()->routeIs('legal.terms')) aria-current="page" @endif class="rounded-md border px-4 py-3 {{ request()->routeIs('legal.terms') ? 'border-blue-100 bg-blue-50 text-blue-700' : 'border-zinc-200 bg-white text-zinc-600 hover:border-blue-200' }}">Syarat &amp; Ketentuan</a>
        <a href="{{ route('legal.privacy') }}" @if(request()->routeIs('legal.privacy')) aria-current="page" @endif class="rounded-md border px-4 py-3 {{ request()->routeIs('legal.privacy') ? 'border-blue-100 bg-blue-50 text-blue-700' : 'border-zinc-200 bg-white text-zinc-600 hover:border-blue-200' }}">Kebijakan Privasi</a>
    </nav>

    <div class="mt-7 grid items-start gap-6 lg:grid-cols-[230px_minmax(0,1fr)] lg:gap-9">
        <nav aria-label="Daftar isi {{ $title }}" class="min-w-0 rounded-lg border border-zinc-200 bg-white p-4 lg:sticky lg:top-28 lg:max-h-[calc(100dvh-144px)] lg:overflow-y-auto">
            <details class="lg:hidden">
                <summary class="min-h-11 cursor-pointer py-3 text-sm font-semibold text-zinc-950">Daftar isi</summary>
                <ol class="mt-2 grid max-h-80 gap-1 overflow-y-auto overscroll-contain">
                    @foreach($sections as $section)
                        <li><a href="#{{ $section['id'] }}" class="block rounded-md px-2 py-2 text-sm leading-6 text-zinc-600 hover:bg-blue-50 hover:text-blue-700">{{ $loop->iteration }}. {{ $section['title'] }}</a></li>
                    @endforeach
                </ol>
            </details>
            <div class="hidden lg:block">
                <p class="px-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">Daftar isi</p>
                <ol class="mt-3 grid gap-1">
                    @foreach($sections as $section)
                        <li><a href="#{{ $section['id'] }}" class="block rounded-md px-2 py-2 text-sm leading-6 text-zinc-600 hover:bg-blue-50 hover:text-blue-700">{{ $loop->iteration }}. {{ $section['title'] }}</a></li>
                    @endforeach
                </ol>
            </div>
        </nav>

        <article data-legal-content data-legal-title="{{ $title }}" data-legal-version="{{ $version }}" data-legal-published="{{ config('legal.published_on') }}" class="min-w-0 rounded-lg border border-zinc-200 bg-white px-5 sm:px-7">
            @foreach($sections as $section)
                <section id="{{ $section['id'] }}" class="scroll-mt-36 border-b border-zinc-100 py-6 last:border-b-0 sm:py-7" aria-labelledby="{{ $section['id'] }}-title">
                    <h2 id="{{ $section['id'] }}-title" class="text-lg font-semibold leading-7 text-zinc-950">{{ $loop->iteration }}. {{ $section['title'] }}</h2>
                    <ul class="mt-4 grid list-disc gap-3 pl-5 text-sm leading-7 text-zinc-600 marker:text-blue-700">
                        @foreach($section['points'] as $point)<li>{{ $point }}</li>@endforeach
                    </ul>
                </section>
            @endforeach
        </article>
    </div>

    <aside class="mt-8 flex flex-col gap-4 rounded-lg border border-blue-100 bg-blue-50 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div><h2 class="font-semibold text-blue-950">Ada pertanyaan tentang aturan atau data akun?</h2><p class="mt-2 max-w-xl text-sm leading-6 text-zinc-600">Untuk masalah transaksi, sertakan kode booking agar admin dapat memeriksa catatan dan bukti yang terkait. Jangan kirim KTP/SIM melalui email biasa.</p></div>
        <a href="mailto:pinjemin99@gmail.com" class="btn btn-primary shrink-0">Hubungi Pinjemin</a>
    </aside>
</section>
