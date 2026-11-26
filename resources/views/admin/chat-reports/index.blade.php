@extends('layouts.admin')
@section('title', 'Laporan Pesan - Pinjemin')
@section('content')
<section class="page-shell">
    <h1 class="text-2xl font-semibold">Laporan Pesan</h1>
    <nav class="my-5 flex flex-wrap gap-2" aria-label="Status laporan">
        @foreach(['open' => 'Menunggu Peninjauan', 'resolved' => 'Ditinjau'] as $filter => $label)
            <a href="{{ route('admin.chat-reports.index', ['status' => $filter]) }}" class="btn {{ $status === $filter ? 'btn-primary' : 'btn-outline' }}" @if($status === $filter) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="grid gap-4">
        @forelse($reports as $report)
            <article class="card card-pad">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="min-w-0 break-words font-semibold">{{ $report->message->conversation->item_name }}</h2>
                    <span class="badge {{ $report->resolved_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $report->resolved_at ? 'Ditinjau' : 'Menunggu Peninjauan' }}</span>
                </div>
                <p class="mt-1 break-words text-sm text-zinc-600">{{ \App\Models\ChatReport::Reasons[$report->reason] }} · Dilaporkan oleh {{ $report->reporter->name }} · {{ $report->created_at->copy()->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }} WIB</p>
                <p class="mt-3 break-words text-sm text-zinc-600">Pesan dari {{ $report->message->sender->name }} · {{ $report->message->created_at->copy()->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }} WIB</p>
                @if($report->message->body !== '')<blockquote class="my-3 rounded-lg bg-zinc-100 p-3 text-sm whitespace-pre-wrap wrap-anywhere">{{ $report->message->body }}</blockquote>@endif
                @if($report->message->attachments)
                    <ul class="my-3 grid gap-1" aria-label="Lampiran yang dilaporkan">
                        @foreach($report->message->attachments as $index => $attachment)
                            <li><a href="{{ route('admin.chat-reports.attachments.show', [$report, $index]) }}" class="inline-flex min-h-11 items-center wrap-anywhere rounded-md border border-zinc-200 px-3 text-sm font-semibold text-brand-700 hover:bg-brand-50">Unduh {{ $attachment['name'] }}</a></li>
                        @endforeach
                    </ul>
                @endif
                @if($report->details)
                    <p class="mb-4 text-sm whitespace-pre-wrap wrap-anywhere">{{ $report->details }}</p>
                @endif
                @if($report->resolved_at)
                    <h3 class="text-sm font-semibold">Catatan Keputusan</h3>
                    <p class="mt-1 text-sm whitespace-pre-wrap wrap-anywhere">{{ $report->resolution }}</p>
                @else
                    <form method="POST" action="{{ route('admin.chat-reports.resolve', $report) }}" class="grid gap-3">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="report_form" value="{{ $report->id }}">
                        @php($hasError = (int) old('report_form') === $report->id && $errors->has('resolution'))
                        <div>
                            <label for="resolution-{{ $report->id }}" class="field-label">Catatan keputusan</label>
                            <textarea id="resolution-{{ $report->id }}" name="resolution" class="input" rows="3" maxlength="2000" required @if($hasError) aria-invalid="true" aria-describedby="resolution-error-{{ $report->id }}" @endif>{{ (int) old('report_form') === $report->id ? old('resolution') : '' }}</textarea>
                            @if($hasError)<p id="resolution-error-{{ $report->id }}" class="field-error">{{ $errors->first('resolution') }}</p>@endif
                        </div>
                        <x-button type="submit" class="w-fit">Catat Keputusan</x-button>
                    </form>
                @endif
            </article>
        @empty
            <p class="rounded-lg border border-zinc-200 bg-white p-5 text-sm text-zinc-600">{{ $status === 'open' ? 'Tidak ada laporan yang menunggu peninjauan.' : 'Belum ada laporan yang ditinjau.' }}</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $reports->links() }}</div>
</section>
@endsection
