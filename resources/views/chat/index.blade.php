@extends('chat.workspace')
@section('title', 'Chat - Pinjemin')

@section('chat_panel')
    <div class="hidden min-w-0 items-center justify-center bg-brand-50/40 p-6 text-center md:flex">
        <div class="max-w-sm">
            <span class="mx-auto inline-flex size-14 items-center justify-center rounded-full bg-brand-100 text-brand-700"><i data-lucide="message-circle" class="size-7" aria-hidden="true"></i></span>
            <h2 class="mt-4 text-2xl font-semibold text-brand-800">Percakapan Anda</h2>
            <p class="mt-2 text-sm leading-6 text-zinc-600">Pilih percakapan yang ingin Anda buka.</p>
        </div>
    </div>
@endsection
