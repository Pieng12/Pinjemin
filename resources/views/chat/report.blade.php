@extends('layouts.app')
@section('title', 'Laporkan Pesan - Pinjemin')
@section('nav_title', 'Pesan')
@section('content')
<section class="page-shell max-w-2xl">
    <a href="{{ route('chat.show', $conversation) }}" class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-brand-700"><i data-lucide="arrow-left" class="size-4" aria-hidden="true"></i> Kembali ke Percakapan</a>
    <article class="mt-3 rounded-lg border border-zinc-200 bg-white p-5">
        <h1 class="text-xl font-semibold">Laporkan Pesan</h1>
        <div class="my-4 grid gap-3 rounded-lg bg-zinc-100 p-3 text-sm">
            @if($message->body !== '')<blockquote class="whitespace-pre-wrap wrap-anywhere">{{ $message->body }}</blockquote>@endif
            @if($message->attachments) @include('chat.attachments') @endif
        </div>
        @if($alreadyReported)
            <p class="text-sm text-zinc-600">Pesan ini sudah pernah Anda laporkan.</p>
        @else
            <p class="mb-4 text-sm leading-6 text-zinc-600">Administrator dapat meninjau pesan yang dipilih beserta lampirannya, alasan laporan, dan keterangan yang Anda kirim.</p>
            <form method="POST" action="{{ route('chat.reports.store', [$conversation, $message]) }}" class="grid gap-4">
                @csrf
                <div>
                    <label for="report-reason" class="field-label">Alasan laporan</label>
                    <select id="report-reason" name="reason" class="input" required @error('reason') aria-invalid="true" aria-describedby="report-reason-error" @enderror>
                        <option value="">Pilih alasan</option>
                        @foreach($reasons as $key => $label)
                            <option value="{{ $key }}" @selected(old('reason') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reason')<p id="report-reason-error" class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="report-details" class="field-label">Keterangan</label>
                    <textarea id="report-details" name="details" class="input" rows="4" maxlength="2000" aria-describedby="report-details-help @error('details')report-details-error @enderror" @error('details') aria-invalid="true" @enderror>{{ old('details') }}</textarea>
                    <p id="report-details-help" class="field-help">Wajib diisi jika memilih Lainnya.</p>
                    @error('details')<p id="report-details-error" class="field-error">{{ $message }}</p>@enderror
                </div>
                <x-button type="submit" class="w-fit">Kirim Laporan</x-button>
            </form>
        @endif
    </article>
</section>
@endsection
