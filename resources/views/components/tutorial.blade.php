<dialog id="pinjemin-tutorial" class="tutorial-dialog" data-tutorial-dialog aria-labelledby="tutorial-title" aria-describedby="tutorial-description">
    <svg class="tutorial-shade" width="100%" height="100%" aria-hidden="true" focusable="false">
        <defs><mask id="tutorial-mask"><rect width="100%" height="100%" fill="white" /><rect data-tutorial-cutout rx="8" fill="black" /></mask></defs>
        <rect width="100%" height="100%" fill="#062c37" fill-opacity="0.72" mask="url(#tutorial-mask)" />
    </svg>
    <div class="tutorial-highlight" data-tutorial-highlight aria-hidden="true"></div>
    <section class="tutorial-card" data-tutorial-card tabindex="-1">
        <div class="tutorial-card-heading"><p class="text-xs font-semibold text-brand-700" data-tutorial-progress></p><button type="button" data-tutorial-close class="tutorial-close" aria-label="Tutup tutorial"><i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i></button></div>
        <h2 id="tutorial-title" class="text-lg font-semibold text-zinc-950"></h2>
        <p id="tutorial-description" class="mt-2 text-sm leading-6 text-zinc-600"></p>
        <progress class="tutorial-progress" data-tutorial-progress-bar aria-label="Progres tutorial"></progress>
        <div class="tutorial-controls"><button type="button" data-tutorial-skip class="tutorial-skip">Lewati</button><div class="flex gap-2"><button type="button" data-tutorial-previous class="btn btn-outline">Sebelumnya</button><button type="button" data-tutorial-next class="btn btn-primary">Berikutnya</button></div></div>
        <p class="mt-3 text-xs leading-5 text-zinc-500">Panduan halaman ini. Gunakan ← / → atau tombol di atas; Esc untuk keluar.</p>
    </section>
    <p class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-tutorial-announcement></p>
</dialog>
