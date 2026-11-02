<dialog id="legal-modal" data-legal-modal data-terms-url="{{ route('legal.terms') }}" data-privacy-url="{{ route('legal.privacy') }}" class="legal-modal" aria-labelledby="legal-modal-title">
    <header class="flex shrink-0 items-center justify-between gap-4 border-b border-zinc-100 px-5 py-4 sm:px-6">
        <div><p class="text-xs font-semibold text-blue-700">Pinjemin</p><h2 id="legal-modal-title" class="mt-1 text-xl font-semibold text-zinc-950">Aturan &amp; Privasi</h2></div>
        <button type="button" data-legal-close autofocus aria-label="Tutup aturan dan privasi" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-md border border-zinc-200 text-zinc-600 transition-colors hover:bg-zinc-50"><i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i></button>
    </header>
    <div role="tablist" aria-label="Pilih dokumen" class="grid shrink-0 grid-cols-2 gap-2 border-b border-zinc-100 p-3 sm:px-6">
        @foreach(['terms' => 'Syarat & Ketentuan', 'privacy' => 'Kebijakan Privasi'] as $document => $label)
            <button id="legal-{{ $document }}-tab" type="button" role="tab" data-legal-tab="{{ $document }}" aria-controls="legal-{{ $document }}-panel" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}" class="min-h-11 rounded-md px-3 py-2 text-sm font-semibold text-zinc-600 transition-colors hover:bg-zinc-50 aria-selected:bg-blue-50 aria-selected:text-blue-700">{{ $label }}</button>
        @endforeach
    </div>
    <div data-legal-scroll class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pb-5 sm:px-6">
        @foreach(['terms' => 'Syarat & Ketentuan', 'privacy' => 'Kebijakan Privasi'] as $document => $label)
            <section id="legal-{{ $document }}-panel" role="tabpanel" data-legal-panel="{{ $document }}" aria-labelledby="legal-{{ $document }}-tab" tabindex="0" @if(!$loop->first) hidden @endif>
                <div class="border-b border-zinc-100 py-5"><h3 data-legal-heading class="text-lg font-semibold text-zinc-950">{{ $label }}</h3><p data-legal-meta class="mt-2 text-xs leading-6 text-zinc-500"></p><p data-legal-summary class="mt-3 text-sm leading-6 text-zinc-600"></p></div>
                <p data-legal-loading role="status" class="py-8 text-sm text-zinc-500">Memuat dokumen...</p>
                <div data-legal-error hidden class="py-6 text-sm leading-6 text-zinc-600"><p>Dokumen belum bisa dimuat. Coba lagi atau baca halaman lengkap.</p><div class="mt-4 flex flex-wrap gap-3"><button type="button" data-legal-retry="{{ $document }}" class="btn btn-outline">Coba Lagi</button><a href="{{ route($document === 'terms' ? 'legal.terms' : 'legal.privacy') }}" data-legal-full-page target="_blank" rel="noopener noreferrer" class="btn btn-outline">Baca Halaman Lengkap</a></div></div>
                <div data-legal-body></div>
            </section>
        @endforeach
    </div>
    <footer class="flex shrink-0 items-center justify-between gap-4 border-t border-zinc-100 bg-zinc-50 px-5 py-4 sm:px-6">
        <p class="text-xs leading-5 text-zinc-500">Tutup untuk melanjutkan aktivitasmu.</p>
        <button type="button" data-legal-close class="btn btn-primary shrink-0">Tutup</button>
    </footer>
</dialog>
