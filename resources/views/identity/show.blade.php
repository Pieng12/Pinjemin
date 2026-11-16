@extends(auth()->user()->isAdmin() ? 'layouts.admin' : 'layouts.app')
@section('title', 'Pengajuan Identitas - Pinjemin')
@section('content')
<section class="page-shell max-w-4xl">
    <div class="page-header"><div><p class="eyebrow">Data privat</p><h1 class="mt-2 text-3xl font-semibold">Pengajuan Identitas #{{ $verification->id }}</h1><p class="mt-2 text-zinc-600">{{ $verification->user->name }} · {{ $verification->statusLabel() }}</p></div><x-button :href="route(auth()->user()->isAdmin() ? 'admin.identity.index' : 'identity.index')" variant="outline">Kembali</x-button></div>
    <div class="card mt-6 p-5">
        <dl class="grid gap-4 text-sm sm:grid-cols-2"><div><dt class="text-zinc-500">Dokumen</dt><dd>{{ strtoupper($verification->document_type) }}</dd></div><div><dt class="text-zinc-500">Nama sesuai dokumen</dt><dd>{{ $verification->legal_name ?? 'Dihapus sesuai retensi' }}</dd></div><div><dt class="text-zinc-500">Nomor identitas</dt><dd>{{ $verification->document_number ? '••••'.substr($verification->document_number, -4) : 'Dihapus sesuai retensi' }}</dd></div><div><dt class="text-zinc-500">Berlaku sampai</dt><dd>{{ $verification->verified_until ? $verification->verified_until->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i').' WIB' : ($verification->status === 'pending' ? 'Menunggu keputusan administrator' : 'Belum ada masa berlaku verifikasi') }}</dd></div></dl>
        @if($verification->review_note)<p class="mt-4 rounded-md bg-zinc-50 p-3 text-sm"><strong>Catatan keputusan:</strong> {{ $verification->review_note }}</p>@endif
        @if($verification->document_path)<a href="{{ route('identity.document', $verification) }}" target="_blank" rel="noopener" class="mt-5 block"><img src="{{ route('identity.document', $verification) }}" alt="Dokumen identitas privat" class="max-h-[32rem] w-full rounded-md border border-zinc-200 bg-white object-contain p-2"></a>@else<p class="mt-5 text-sm text-zinc-500">Gambar identitas sudah dihapus sesuai masa penyimpanan.</p>@endif
        @if($verification->hold_reason)<p class="mt-4 rounded-md bg-amber-50 p-3 text-sm">Penundaan penghapusan: {{ $verification->hold_reason }} · Tinjau {{ $verification->hold_review_at?->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y') }}.</p>@endif
    </div>
    @can('update', $verification)
        @php
            $identityPageErrors = $errors;
            if (old('_ui_form') && old('_ui_form') !== 'identity-review') {
                $errors = new \Illuminate\Support\ViewErrorBag;
            }
        @endphp
        <form id="identity-review" data-form-id="identity-review" method="POST" action="{{ route('admin.identity.review', $verification) }}" class="card mt-5 grid gap-4 p-5">@csrf<input type="hidden" name="_ui_form" value="identity-review"><h2 class="font-semibold">Pemeriksaan Administrator</h2><x-validation-summary bag="default" form-id="identity-review" :field-ids="['action' => 'identity-review-action', 'note' => 'identity-review-note']" /><label for="identity-review-action"><span class="field-label">Tindakan</span><select id="identity-review-action" name="action" required class="input" aria-invalid="{{ $errors->has('action') ? 'true' : 'false' }}" @error('action') aria-describedby="identity-review-action-error" @enderror>@if($verification->status === 'pending')<option value="verify" @selected((!old('_ui_form') || old('_ui_form') === 'identity-review') && old('action') === 'verify')>Setujui verifikasi</option><option value="reject" @selected((!old('_ui_form') || old('_ui_form') === 'identity-review') && old('action') === 'reject')>Minta perbaikan</option>@endif @if(in_array($verification->status, ['pending', 'verified'], true))<option value="revoke" @selected((!old('_ui_form') || old('_ui_form') === 'identity-review') && old('action') === 'revoke')>Cabut verifikasi</option>@endif<option value="hold" @selected((!old('_ui_form') || old('_ui_form') === 'identity-review') && old('action') === 'hold')>Tahan penghapusan / tinjau ulang 30 hari</option><option value="release_hold" @selected((!old('_ui_form') || old('_ui_form') === 'identity-review') && old('action') === 'release_hold')>Akhiri penundaan penghapusan</option><option value="disclosure" @selected((!old('_ui_form') || old('_ui_form') === 'identity-review') && old('action') === 'disclosure')>Catat pengungkapan kepada pihak berwenang</option></select>@error('action')<span id="identity-review-action-error" data-error-field="action" class="field-error">{{ $message }}</span>@enderror</label><label for="identity-review-note"><span class="field-label">Alasan / dasar tindakan</span><textarea id="identity-review-note" name="note" maxlength="2000" rows="3" class="input" aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}" aria-describedby="identity-review-note-help @error('note') identity-review-note-error @enderror"></textarea>@error('note')<span id="identity-review-note-error" data-error-field="note" class="field-error">{{ $message }}</span>@enderror<span id="identity-review-note-help" class="field-help">Wajib selain persetujuan. Jangan menyalin NIK atau nomor dokumen ke catatan. Untuk pengungkapan, catat instansi, dasar sah, tujuan, dan referensi permintaan; aplikasi tidak mengirim dokumen otomatis.</span></label><x-button type="submit" class="w-fit">Catat Keputusan</x-button></form>
        @php
            $errors = $identityPageErrors;
        @endphp
    @endcan
    @can('withdraw', $verification)
        @php
            $identityPageErrors = $errors;
            if (old('_ui_form') && old('_ui_form') !== 'identity-withdraw') {
                $errors = new \Illuminate\Support\ViewErrorBag;
            }
        @endphp
        <details class="card mt-5 p-5" @if(old('_ui_form') === 'identity-withdraw' && $errors->any()) open @endif><summary class="cursor-pointer text-sm font-semibold">Tarik Pengajuan / Verifikasi</summary><form id="identity-withdraw" data-form-id="identity-withdraw" method="POST" action="{{ route('identity.withdraw', $verification) }}" class="mt-3 grid gap-3">@csrf<input type="hidden" name="_ui_form" value="identity-withdraw"><p class="text-sm text-zinc-600">Penarikan dapat memblokir penyerahan pemesanan yang mewajibkan identitas. Data kasus aktif tidak langsung dihapus.</p><x-validation-summary bag="default" form-id="identity-withdraw" :field-ids="['note' => 'identity-withdraw-note']" /><label for="identity-withdraw-note"><span class="field-label">Alasan</span><textarea id="identity-withdraw-note" name="note" rows="2" required maxlength="2000" class="input" aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}" @error('note') aria-describedby="identity-withdraw-note-error" @enderror></textarea>@error('note')<span id="identity-withdraw-note-error" data-error-field="note" class="field-error">{{ $message }}</span>@enderror</label><x-button type="submit" variant="outline" class="w-fit">Tarik Pengajuan</x-button></form></details>
        @php
            $errors = $identityPageErrors;
        @endphp
    @endcan
    <details class="mt-6"><summary class="cursor-pointer text-sm font-semibold">Riwayat keputusan dan akses administrator</summary><ol class="mt-3 grid gap-2 text-sm text-zinc-600">@foreach($events as $event)<li>{{ $event->action }} · {{ \Illuminate\Support\Carbon::parse($event->created_at)->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB @if($event->note)<p>{{ $event->note }}</p>@endif</li>@endforeach</ol></details>
</section>
@endsection
