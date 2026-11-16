@if($booking->usesConditionInspections())
<section id="booking-inspections" tabindex="-1" class="mt-7 grid scroll-mt-24 gap-5" aria-labelledby="inspections-title" data-inspection-panel data-status-url="{{ route('inspections.status', $booking) }}">
    @php
$policy = $booking->fulfillment_snapshot['inspection_policy'];
@endphp
    @php
$isParty = auth()->user()->can('participateInspection', $booking);
@endphp
    @php
$isOwner = auth()->id() === $booking->item->user_id;
@endphp
    @php
$inspectionActions = in_array($booking->status, [\App\BookingStatus::Approved, \App\BookingStatus::Completed], true) && !in_array($booking->payout?->status, ['processing', 'paid', 'cancelled'], true);
@endphp
    <a href="{{ route('legal.terms') }}#kondisi" target="_blank" rel="noopener noreferrer" class="w-fit text-sm font-semibold text-blue-700 underline underline-offset-2">Panduan foto, pengembalian, dan pengaduan</a>
    <div><h2 id="inspections-title" class="text-xl font-semibold">Pemeriksaan &amp; Berita Acara</h2><p class="mt-2 text-sm leading-6 text-zinc-600">Foto kondisi {{ $policy['photos_required'] ? 'wajib dari kedua pihak' : 'opsional dan tetap boleh diunggah' }}. Bukti yang sudah dikirim dikunci. Waktu unggah bukan jaminan waktu pengambilan foto. Ketentuan {{ $policy['terms_version'] }}.</p></div>
    @if($policy['item_rules'] ?? null)<p class="rounded-md bg-zinc-50 p-4 text-sm whitespace-pre-line"><strong>Aturan barang saat pengajuan:</strong> {{ $policy['item_rules'] }}</p>@endif
    @if($policy['identity_required'])
        @php
$identityStatus = $booking->identityVerification ?? $booking->renter->currentIdentity();
@endphp
        <div class="rounded-md border border-zinc-200 bg-white p-4 text-sm"><strong>Persyaratan identitas penyewa:</strong> {{ $identityStatus?->statusLabel() ?? 'Belum Diajukan' }}. Dokumen hanya dapat dilihat penyewa dan admin.
            @if(auth()->id() === $booking->renter_user_id)<a href="{{ route('identity.index') }}" class="mt-2 block font-semibold text-brand underline">Kelola verifikasi identitas</a>@endif
            @if(auth()->user()->isAdmin() && $identityStatus)<a href="{{ route('identity.show', $identityStatus) }}" class="mt-2 block font-semibold text-brand underline">Periksa identitas secara privat</a>@endif
        </div>
    @endif
    <p hidden data-inspection-changed class="rounded-md bg-blue-50 p-3 text-sm text-blue-900">Ada pembaruan pemeriksaan. Simpan draf sebelum <a href="{{ url()->current() }}" class="font-semibold underline">memuat ulang halaman</a>.</p>
    @if($booking->returned_at)<p class="rounded-md bg-emerald-50 p-4 text-sm text-emerald-900">Penerimaan fisik dikonfirmasi {{ $booking->returned_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB. Stok sudah dibebaskan; pencairan masih mengikuti hasil pemeriksaan kedua pihak.</p>@endif
    @foreach(['handover' => 'Penyerahan', 'return' => 'Pengembalian'] as $stage => $stageLabel)
        @php
$sessions = $booking->conditionSessions->where('stage', $stage)->sortBy('attempt');
@endphp
        @php
$session = $sessions->last();
@endphp
        @php
$mine = $session?->reports->where('kind', 'initial')->where('user_id', auth()->id())->first();
@endphp
        @php
$stageAvailable = $inspectionActions && ($stage === 'handover' || $booking->handed_over_at) && ($booking->handed_over_at || !($handoverBlocker ?? null));
@endphp
        <article id="booking-inspections-{{ $stage }}" tabindex="-1" class="card scroll-mt-24 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="text-lg font-semibold">{{ $stageLabel }}</h3><span class="badge bg-zinc-100 text-zinc-700">{{ $session?->statusLabel() ?? 'Belum dimulai' }}</span></div>
            @if($booking->fulfillment_method === \App\Models\Booking::Delivery)<p class="mt-3 text-sm leading-6 text-zinc-600">{{ $stage === 'handover' ? 'Pemilik mengirim bukti kondisi dan kemasan sebelum penyewa mencatat kondisi saat paket diterima.' : 'Penyewa mengirim bukti saat pengiriman balik, lalu pemilik mencatat kondisi saat menerima kembali.' }} Waktu masing-masing dicatat terpisah.</p>@else<p class="mt-3 text-sm leading-6 text-zinc-600">Kedua pihak menyatakan siap. Sesi unggah bersama berlangsung 30 menit; persetujuan diberikan setelah bukti kedua pihak terkirim.</p>@endif
            @if($session?->bothSubmitted())
                <p class="mt-2 text-sm text-zinc-500">Bukti kedua pihak sudah terkirim. Hasil pemeriksaan mengikuti keputusan kedua pihak atau penyelesaian administrator.</p>
            @elseif($session?->expires_at)
                <p class="mt-2 text-sm text-zinc-500">Batas unggah: {{ $session->expires_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB <span data-inspection-deadline="{{ $session->expires_at->toIso8601String() }}"></span>. Tidak ada persetujuan otomatis saat sesi berakhir.</p>
            @endif
            @if($isParty && $stageAvailable)
                @if(!$session || in_array($session->status, ['waiting', 'active', 'expired'], true) || $session->hasExpired())
                    @php
                        $bookingFormKey = 'inspection-form-1'.'-'.$stage;
                        $bookingFormErrors = $errors;
                        $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                        $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                        $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                    @endphp
                    <form method="POST" action="{{ route('inspections.store', [$booking, $stage]) }}" class="mt-4" data-form-id="inspection-form-1-{{ $stage }}">
                        <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                        <x-validation-summary :form-id="$bookingFormKey" :field-ids="[]" />
@csrf<input type="hidden" name="action" value="ready">@if($session)<input type="hidden" name="session_id" value="{{ $session->id }}">@endif<x-button type="submit" variant="outline">{{ $session?->hasExpired() || $session?->status === 'expired' ? 'Siap untuk Sesi Baru' : 'Saya Siap Memeriksa' }}</x-button></form>
                    @php
                        $errors = $bookingFormErrors;
                    @endphp

                @endif
                @if($session && $session->status === 'active' && !$session->hasExpired() && !$mine?->submitted_at)
                    @php
                        $bookingFormKey = 'inspection-form-2'.'-'.$stage;
                        $bookingFormErrors = $errors;
                        $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                        $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                        $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                    @endphp
                    <form method="POST" action="{{ route('inspections.store', [$booking, $stage]) }}" enctype="multipart/form-data" class="mt-5 grid gap-4 border-t border-zinc-200 pt-5" data-condition-upload data-form-id="inspection-form-2-{{ $stage }}">
                        <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                        <x-validation-summary :form-id="$bookingFormKey" :field-ids="['function_state' => 'inspection-form-2-'. $stage .'-function_state', 'accessories' => 'inspection-form-2-'. $stage .'-accessories', 'serial_number' => 'inspection-form-2-'. $stage .'-serial_number', 'condition_note' => 'inspection-form-2-'. $stage .'-condition_note', 'photos' => 'inspection-form-2-'. $stage .'-photos', 'replace_photos' => 'inspection-form-2-'. $stage .'-replace_photos', 'physical_confirmation' => 'inspection-form-2-'. $stage .'-physical_confirmation']" />

                        @csrf<input type="hidden" name="session_id" value="{{ $session->id }}">
                        <label><span class="field-label">Hasil uji fungsi</span><select name="function_state" class="input" id="inspection-form-2-{{ $stage }}-function_state" aria-describedby="inspection-form-2-{{ $stage }}-function_state-error" aria-invalid="{{ $errors->has('function_state') ? 'true' : 'false' }}"><option value="">Pilih hasil pemeriksaan</option>@foreach(['working' => 'Berfungsi', 'issues' => 'Ada masalah', 'untested' => 'Belum dapat diuji'] as $value => $label)<option value="{{ $value }}" @selected($bookingOld('function_state', $mine?->function_state) === $value)>{{ $label }}</option>@endforeach</select>@error('function_state')<span id="inspection-form-2-{{ $stage }}-function_state-error" data-error-field="function_state" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                        <label><span class="field-label">Kelengkapan / aksesori</span><textarea name="accessories" rows="2" maxlength="2000" class="input" placeholder="Contoh: charger, tas, baterai; tulis jika ada yang kurang" id="inspection-form-2-{{ $stage }}-accessories" aria-describedby="inspection-form-2-{{ $stage }}-accessories-error" aria-invalid="{{ $errors->has('accessories') ? 'true' : 'false' }}">{{ $bookingOld('accessories', $mine?->accessories) }}</textarea>@error('accessories')<span id="inspection-form-2-{{ $stage }}-accessories-error" data-error-field="accessories" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                        <label><span class="field-label">Nomor seri (jika ada)</span><input name="serial_number" value="{{ $bookingOld('serial_number', $mine?->serial_number) }}" maxlength="255" class="input" id="inspection-form-2-{{ $stage }}-serial_number" aria-describedby="inspection-form-2-{{ $stage }}-serial_number-error" aria-invalid="{{ $errors->has('serial_number') ? 'true' : 'false' }}">@error('serial_number')<span id="inspection-form-2-{{ $stage }}-serial_number-error" data-error-field="serial_number" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                        <label><span class="field-label">Kondisi dan cacat yang ditemukan</span><textarea name="condition_note" rows="3" maxlength="2000" class="input" id="inspection-form-2-{{ $stage }}-condition_note" aria-describedby="inspection-form-2-{{ $stage }}-condition_note-error" aria-invalid="{{ $errors->has('condition_note') ? 'true' : 'false' }}">{{ $bookingOld('condition_note', $mine?->condition_note) }}</textarea>@error('condition_note')<span id="inspection-form-2-{{ $stage }}-condition_note-error" data-error-field="condition_note" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                        <label><span class="field-label">Foto kondisi {{ $policy['photos_required'] ? '(wajib sebelum dikirim)' : '(opsional)' }}</span><input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="photo-file-input" data-condition-photos id="inspection-form-2-{{ $stage }}-photos" aria-describedby="inspection-form-2-{{ $stage }}-photos-error" aria-invalid="{{ $errors->has('photos') ? 'true' : 'false' }}">@error('photos')<span id="inspection-form-2-{{ $stage }}-photos-error" data-error-field="photos" class="field-error" role="alert">{{ $message }}</span>@enderror<span class="field-help">Total maksimal 5 foto pada bukti awal; JPG, PNG, atau WebP, masing-masing maksimal 5 MB. Dokumentasikan keseluruhan, bagian penting, cacat, dan aksesori. Jangan sertakan KTP/SIM.</span></label>
                        <div hidden data-condition-preview class="grid grid-cols-2 gap-3"></div><p hidden data-condition-error class="field-error" role="alert"></p>
                        @if($mine?->photos)
                            <div class="grid grid-cols-2 gap-3">@foreach($mine->photos as $photo)<img src="{{ route('inspections.photo', [$booking, $mine, $photo['id']]) }}" alt="Foto draf kondisi" class="h-36 w-full rounded-md border border-zinc-200 object-contain">@endforeach</div><label class="flex gap-2 text-sm"><input type="checkbox" name="replace_photos" value="1" id="inspection-form-2-{{ $stage }}-replace_photos" aria-describedby="inspection-form-2-{{ $stage }}-replace_photos-error" aria-invalid="{{ $errors->has('replace_photos') ? 'true' : 'false' }}">@error('replace_photos')<span id="inspection-form-2-{{ $stage }}-replace_photos-error" data-error-field="replace_photos" class="field-error" role="alert">{{ $message }}</span>@enderror Ganti seluruh foto draf dengan pilihan baru</label>
                        @endif
                        @if($booking->fulfillment_method === \App\Models\Booking::Delivery && $isOwner)<label class="flex items-start gap-3 text-sm"><input type="checkbox" name="physical_confirmation" value="1" class="mt-1" id="inspection-form-2-{{ $stage }}-physical_confirmation" aria-describedby="inspection-form-2-{{ $stage }}-physical_confirmation-error" aria-invalid="{{ $errors->has('physical_confirmation') ? 'true' : 'false' }}">@error('physical_confirmation')<span id="inspection-form-2-{{ $stage }}-physical_confirmation-error" data-error-field="physical_confirmation" class="field-error" role="alert">{{ $message }}</span>@enderror<span>{{ $stage === 'handover' ? 'Barang sudah saya serahkan ke kurir. Pembatalan biasa tidak tersedia setelah dicatat.' : 'Barang sudah saya terima kembali secara fisik. Stok dapat dibebaskan meskipun kondisi masih diperiksa.' }}</span></label>@endif
                        <div class="flex flex-wrap gap-2"><button type="submit" name="action" value="save" class="btn btn-outline">Simpan Draf</button><button type="submit" name="action" value="send" class="btn btn-primary">Kirim &amp; Kunci Bukti</button></div>
                    </form>
                    @php
                        $errors = $bookingFormErrors;
                    @endphp

                @endif
                @if($session)
                    <details class="mt-4 rounded-md border border-zinc-200 p-3"><summary class="cursor-pointer text-sm font-semibold">Bukti Tambahan / Bantuan Administrator</summary>@php
    $bookingFormKey = 'inspection-form-3'.'-'.$stage;
    $bookingFormErrors = $errors;
    $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
    $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
    $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
@endphp
<form method="POST" action="{{ route('inspections.store', [$booking, $stage]) }}" enctype="multipart/form-data" class="mt-3 grid gap-3" data-condition-upload data-form-id="inspection-form-3-{{ $stage }}">
<input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
<x-validation-summary :form-id="$bookingFormKey" :field-ids="['note' => 'inspection-form-3-'. $stage .'-note', 'photos' => 'inspection-form-3-'. $stage .'-photos', 'request_admin_review' => 'inspection-form-3-'. $stage .'-request_admin_review']" />
@csrf<input type="hidden" name="action" value="supplement"><input type="hidden" name="session_id" value="{{ $session->id }}"><label><span class="field-label">Penjelasan tambahan</span><textarea name="note" required maxlength="2000" rows="2" class="input" id="inspection-form-3-{{ $stage }}-note" aria-describedby="inspection-form-3-{{ $stage }}-note-error" aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}">{{ $bookingOld('note') }}</textarea>@error('note')<span id="inspection-form-3-{{ $stage }}-note-error" data-error-field="note" class="field-error" role="alert">{{ $message }}</span>@enderror</label><input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="photo-file-input" data-condition-photos id="inspection-form-3-{{ $stage }}-photos" aria-describedby="inspection-form-3-{{ $stage }}-photos-error" aria-invalid="{{ $errors->has('photos') ? 'true' : 'false' }}">@error('photos')<span id="inspection-form-3-{{ $stage }}-photos-error" data-error-field="photos" class="field-error" role="alert">{{ $message }}</span>@enderror<div hidden data-condition-preview class="grid grid-cols-2 gap-3"></div><p hidden data-condition-error class="field-error" role="alert"></p>
@if(!$session->resolved_at)
<label class="flex items-start gap-3 text-sm"><input type="checkbox" name="request_admin_review" value="1" class="mt-1" id="inspection-form-3-{{ $stage }}-request_admin_review" aria-describedby="inspection-form-3-{{ $stage }}-request_admin_review-error" aria-invalid="{{ $errors->has('request_admin_review') ? 'true' : 'false' }}">@error('request_admin_review')<span id="inspection-form-3-{{ $stage }}-request_admin_review-error" data-error-field="request_admin_review" class="field-error" role="alert">{{ $message }}</span>@enderror<span>Minta pemeriksaan administrator karena ada masalah atau pihak lain tidak kooperatif. Pencairan akan ditahan.</span></label>
@endif
<p class="text-xs text-zinc-500">Tambahan bukti tidak menggantikan bukti awal atau mengubah keputusan yang sudah dicatat.</p>
<x-button type="submit" class="w-fit" variant="outline">Kirim Tambahan Bukti</x-button></form>
@php
$errors = $bookingFormErrors;
@endphp
</details>
                @endif
                @if($session?->bothSubmitted() && !$session->hasExpired() && $mine?->submitted_at && !$mine->decision && !$session->resolved_at)
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">@php
    $bookingFormKey = 'inspection-form-4'.'-'.$stage;
    $bookingFormErrors = $errors;
    $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
    $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
    $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
@endphp
<form method="POST" action="{{ route('inspections.store', [$booking, $stage]) }}" data-form-id="inspection-form-4-{{ $stage }}">
<input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
<x-validation-summary :form-id="$bookingFormKey" :field-ids="[]" />
@csrf<input type="hidden" name="session_id" value="{{ $session->id }}"><input type="hidden" name="action" value="agree"><x-button type="submit">Kondisi Sesuai</x-button></form>
@php
$errors = $bookingFormErrors;
@endphp
@php
    $bookingFormKey = 'inspection-form-5'.'-'.$stage;
    $bookingFormErrors = $errors;
    $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
    $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
    $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
@endphp
<form method="POST" action="{{ route('inspections.store', [$booking, $stage]) }}" class="grid gap-2" data-form-id="inspection-form-5-{{ $stage }}">
    <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
    <x-validation-summary :form-id="$bookingFormKey" :field-ids="['note' => 'inspection-form-5-'. $stage .'-note']" />
@csrf<input type="hidden" name="session_id" value="{{ $session->id }}"><input type="hidden" name="action" value="dispute"><label><span class="field-label">Perbedaan yang ditemukan</span><textarea name="note" required maxlength="2000" rows="2" class="input" id="inspection-form-5-{{ $stage }}-note" aria-describedby="inspection-form-5-{{ $stage }}-note-error" aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}">{{ $bookingOld('note') }}</textarea>@error('note')<span id="inspection-form-5-{{ $stage }}-note-error" data-error-field="note" class="field-error" role="alert">{{ $message }}</span>@enderror</label><x-button type="submit" variant="danger">Ada Perbedaan</x-button></form>
@php
    $errors = $bookingFormErrors;
@endphp
</div>
                @endif
                @if($isOwner && ($stage === 'return' ? !$booking->returned_at : !$booking->handed_over_at))
                    @php
                        $bookingFormKey = 'inspection-form-6'.'-'.$stage;
                        $bookingFormErrors = $errors;
                        $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                        $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                        $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                    @endphp
                    <form method="POST" action="{{ route('inspections.store', [$booking, $stage]) }}" class="mt-5 rounded-md bg-zinc-50 p-4" data-form-id="inspection-form-6-{{ $stage }}">
                        <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                        <x-validation-summary :form-id="$bookingFormKey" :field-ids="['physical_confirmation' => 'inspection-form-6-'. $stage .'-physical_confirmation']" />
@csrf<input type="hidden" name="action" value="physical">@if($session)<input type="hidden" name="session_id" value="{{ $session->id }}">@endif<label class="flex items-start gap-3 text-sm leading-6"><input type="checkbox" name="physical_confirmation" value="1" required class="mt-1" id="inspection-form-6-{{ $stage }}-physical_confirmation" aria-describedby="inspection-form-6-{{ $stage }}-physical_confirmation-error" aria-invalid="{{ $errors->has('physical_confirmation') ? 'true' : 'false' }}">@error('physical_confirmation')<span id="inspection-form-6-{{ $stage }}-physical_confirmation-error" data-error-field="physical_confirmation" class="field-error" role="alert">{{ $message }}</span>@enderror<span>{{ $stage === 'return' ? 'Saya sudah menerima barang kembali secara fisik. Konfirmasi ini membebaskan stok, bukan menyetujui kondisi atau pencairan.' : 'Barang sudah saya serahkan secara fisik atau kepada kurir. Konfirmasi ini mengunci pembatalan, bukan menyatakan kedua pihak setuju dengan kondisi.' }}</span></label><x-button type="submit" variant="outline" class="mt-3">{{ $stage === 'return' ? 'Konfirmasi Penerimaan Fisik' : 'Catat Penyerahan Fisik' }}</x-button></form>
                    @php
                        $errors = $bookingFormErrors;
                    @endphp

                @endif
            @elseif(!$stageAvailable)<p class="mt-4 text-sm text-zinc-500">{{ $handoverBlocker ?? ($inspectionActions ? 'Tersedia setelah penyerahan fisik dicatat.' : 'Tindakan tersedia setelah pembayaran lunas, sebelum pencairan diproses.') }}</p>@endif
            @foreach($sessions as $history)
                <details class="mt-5 border-t border-zinc-200 pt-4" @if($history->id === $session?->id) open @endif><summary class="cursor-pointer text-sm font-semibold">Sesi {{ $history->attempt }} · {{ $history->statusLabel() }}</summary>
                    <div class="mt-3 grid gap-3">
                    @foreach($history->reports as $report)
                        @if($report->user_id === auth()->id() || ($report->submitted_at && $history->canSeeOthers(auth()->user())))
                            <article class="rounded-md border border-zinc-200 p-4 text-sm"><strong>{{ match($report->role) {'owner' => 'Pemilik', 'renter' => 'Penyewa', default => 'Administrator'} }} · {{ $report->kind === 'initial' ? 'Bukti awal' : ($report->kind === 'admin' ? 'Dasar keputusan' : 'Tambahan bukti') }}</strong><p class="mt-1 text-zinc-500">{{ $report->submitted_at ? $report->submitted_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i:s').' WIB' : 'Draf belum dikirim' }}</p><p class="mt-3">Fungsi: {{ match($report->function_state) {'working' => 'Berfungsi', 'issues' => 'Ada masalah', 'untested' => 'Belum dapat diuji', default => 'Belum dicatat'} }}</p><p class="mt-1 whitespace-pre-line">Aksesori: {{ $report->accessories ?: 'Belum dicatat' }}</p><p class="mt-1 break-words">Nomor seri: {{ $report->serial_number ?: 'Belum dicatat' }}</p><p class="mt-2 whitespace-pre-line">{{ $report->condition_note }}</p><div class="mt-3 grid grid-cols-2 gap-3">@foreach($report->photos ?? [] as $photo)<a href="{{ route('inspections.photo', [$booking, $report, $photo['id']]) }}" target="_blank" rel="noopener"><img src="{{ route('inspections.photo', [$booking, $report, $photo['id']]) }}" alt="Bukti kondisi {{ $report->role }}" class="h-40 w-full rounded-md border border-zinc-200 bg-white object-contain"></a>@endforeach</div><p class="mt-3 font-semibold">{{ match($report->decision) {'agree' => 'Kondisi sesuai', 'dispute' => 'Ada perbedaan kondisi', default => 'Belum memberi keputusan'} }}</p>@if($report->decision_note)<p class="mt-1 text-red-800">{{ $report->decision_note }}</p>@endif</article>
                        @endif
                    @endforeach
                    @if(!$history->canSeeOthers(auth()->user()))<p class="text-sm text-zinc-500">Dokumentasi pihak lain terbuka setelah kedua pihak mengirim bukti atau sesi berakhir.</p>@endif
                    @if($history->resolution_note)<p class="rounded-md bg-amber-50 p-3 text-sm"><strong>Keputusan administrator:</strong> {{ $history->resolution_note }}. Ini bukan persetujuan atas nama pengguna.</p>@endif
                    <x-button :href="route('inspections.document', [$booking, $history])" variant="outline" class="w-fit">Unduh Berita Acara{{ $history->isSettled() ? '' : ' Belum Lengkap' }}</x-button>
                    </div>
                </details>
            @endforeach
            @can('resolveInspection', $booking)
                @if($stageAvailable)
                <details class="mt-5 rounded-md border border-amber-200 p-4" @if(old('_ui_form') === 'inspection-form-7-'.$stage) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold">Pemeriksaan Administrator / Pihak Tidak Kooperatif</summary>
                    @php
                        $bookingFormKey = 'inspection-form-7'.'-'.$stage;
                        $bookingFormErrors = $errors;
                        $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                        $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                        $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                    @endphp
                    <form method="POST" action="{{ route('inspections.store', [$booking, $stage]) }}" enctype="multipart/form-data" class="mt-4 grid gap-3" data-condition-upload data-form-id="inspection-form-7-{{ $stage }}">
                        <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                        <x-validation-summary :form-id="$bookingFormKey" :field-ids="['outcome' => 'inspection-form-7-'. $stage .'-outcome', 'physical_event' => 'inspection-form-7-'. $stage .'-physical_event', 'note' => 'inspection-form-7-'. $stage .'-note', 'evidence_report_id' => 'inspection-form-7-'. $stage .'-evidence_report_id', 'photos' => 'inspection-form-7-'. $stage .'-photos']" />

                        @csrf
                        <input type="hidden" name="action" value="resolve">
                        @if($session)<input type="hidden" name="session_id" value="{{ $session->id }}">@endif
                        <label><span class="field-label">Hasil pemeriksaan</span><select name="outcome" required class="input" id="inspection-form-7-{{ $stage }}-outcome" aria-describedby="inspection-form-7-{{ $stage }}-outcome-error" aria-invalid="{{ $errors->has('outcome') ? 'true' : 'false' }}"><option value="confirmed">Konfirmasi pemeriksaan berdasarkan bukti</option><option value="financial_review">Tetap tahan dana; perlu penanganan keuangan</option></select>@error('outcome')<span id="inspection-form-7-{{ $stage }}-outcome-error" data-error-field="outcome" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                        <label><span class="field-label">Peristiwa fisik yang dibuktikan</span><select name="physical_event" class="input" id="inspection-form-7-{{ $stage }}-physical_event" aria-describedby="inspection-form-7-{{ $stage }}-physical_event-error" aria-invalid="{{ $errors->has('physical_event') ? 'true' : 'false' }}"><option value="none">Tidak mengubah catatan fisik</option><option value="handed_over">Barang telah diserahkan</option><option value="returned">Barang telah diterima kembali oleh pemilik</option></select>@error('physical_event')<span id="inspection-form-7-{{ $stage }}-physical_event-error" data-error-field="physical_event" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                        <label><span class="field-label">Alasan dan dasar keputusan</span><textarea name="note" required maxlength="2000" rows="3" class="input" id="inspection-form-7-{{ $stage }}-note" aria-describedby="inspection-form-7-{{ $stage }}-note-error" aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}">{{ $bookingOld('note') }}</textarea>@error('note')<span id="inspection-form-7-{{ $stage }}-note-error" data-error-field="note" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                        <label><span class="field-label">Pilih bukti foto pemesanan yang sudah dikirim</span><select name="evidence_report_id" class="input" id="inspection-form-7-{{ $stage }}-evidence_report_id" aria-describedby="inspection-form-7-{{ $stage }}-evidence_report_id-error" aria-invalid="{{ $errors->has('evidence_report_id') ? 'true' : 'false' }}"><option value="">Unggah bukti baru di bawah</option>
                            @foreach($booking->conditionSessions as $sourceSession)
                                @foreach($sourceSession->reports as $sourceReport)
                                    @if($sourceReport->submitted_at && $sourceReport->photos)
                                        <option value="{{ $sourceReport->id }}">#{{ $sourceReport->id }} · {{ $sourceSession->stage }} · {{ $sourceReport->role }}</option>
                                    @endif
                                @endforeach
                            @endforeach
                        </select>@error('evidence_report_id')<span id="inspection-form-7-{{ $stage }}-evidence_report_id-error" data-error-field="evidence_report_id" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                        <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="photo-file-input" data-condition-photos id="inspection-form-7-{{ $stage }}-photos" aria-describedby="inspection-form-7-{{ $stage }}-photos-error" aria-invalid="{{ $errors->has('photos') ? 'true' : 'false' }}">@error('photos')<span id="inspection-form-7-{{ $stage }}-photos-error" data-error-field="photos" class="field-error" role="alert">{{ $message }}</span>@enderror
                        <div hidden data-condition-preview class="grid grid-cols-2 gap-3"></div><p hidden data-condition-error class="field-error" role="alert"></p>
                        <p class="text-xs leading-5 text-zinc-500">Wajib memakai bukti foto dari pemesanan ini atau unggahan baru. Tidak mengubah nominal, menyetujui atas nama pengguna, atau otomatis melepas penahanan pencairan.</p>
                        <x-button type="submit" class="w-fit">Catat Keputusan Administrator</x-button>
                    </form>
                    @php
                        $errors = $bookingFormErrors;
                    @endphp

                </details>
                @endif
            @endcan
        </article>
    @endforeach
</section>
@endif
