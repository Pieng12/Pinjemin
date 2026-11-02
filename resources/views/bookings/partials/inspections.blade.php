@if($booking->usesConditionInspections())
<section class="mt-7 grid gap-5" aria-labelledby="inspections-title" data-inspection-panel data-status-url="{{ route('inspections.status', $booking) }}">
    @php($policy = $booking->fulfillment_snapshot['inspection_policy'])
    @php($isParty = auth()->user()->can('participateInspection', $booking))
    @php($isOwner = auth()->id() === $booking->item->user_id)
    @php($inspectionActions = in_array($booking->status, [\App\BookingStatus::Approved, \App\BookingStatus::Completed], true) && !in_array($booking->payout?->status, ['processing', 'paid', 'cancelled'], true))
    <a href="{{ route('legal.terms') }}#kondisi" target="_blank" rel="noopener noreferrer" class="w-fit text-sm font-semibold text-blue-700 underline underline-offset-2">Panduan foto, pengembalian, dan pengaduan</a>
    <div><h2 id="inspections-title" class="text-xl font-semibold">Pemeriksaan &amp; Berita Acara</h2><p class="mt-2 text-sm leading-6 text-zinc-600">Foto kondisi {{ $policy['photos_required'] ? 'wajib dari kedua pihak' : 'opsional dan tetap boleh diunggah' }}. Bukti yang sudah dikirim dikunci. Waktu unggah bukan jaminan waktu pengambilan foto. Ketentuan {{ $policy['terms_version'] }}.</p></div>
    @if($policy['item_rules'] ?? null)<p class="rounded-md bg-zinc-50 p-4 text-sm whitespace-pre-line"><strong>Aturan barang saat pengajuan:</strong> {{ $policy['item_rules'] }}</p>@endif
    @if($policy['identity_required'])
        @php($identityStatus = $booking->identityVerification ?? $booking->renter->currentIdentity())
        <div class="rounded-md border border-zinc-200 bg-white p-4 text-sm"><strong>Persyaratan identitas penyewa:</strong> {{ $identityStatus?->statusLabel() ?? 'Belum Diajukan' }}. Dokumen hanya dapat dilihat penyewa dan admin.
            @if(auth()->id() === $booking->renter_user_id)<a href="{{ route('identity.index') }}" class="mt-2 block font-semibold text-brand underline">Kelola verifikasi identitas</a>@endif
            @if(auth()->user()->isAdmin() && $identityStatus)<a href="{{ route('identity.show', $identityStatus) }}" class="mt-2 block font-semibold text-brand underline">Periksa identitas secara privat</a>@endif
        </div>
    @endif
    <p hidden data-inspection-changed class="rounded-md bg-blue-50 p-3 text-sm text-blue-900">Ada pembaruan pemeriksaan. Simpan draf sebelum <a href="{{ url()->current() }}" class="font-semibold underline">memuat ulang halaman</a>.</p>
    @if($booking->returned_at)<p class="rounded-md bg-emerald-50 p-4 text-sm text-emerald-900">Penerimaan fisik dikonfirmasi {{ $booking->returned_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB. Stok sudah dibebaskan; pencairan masih mengikuti hasil pemeriksaan kedua pihak.</p>@endif
    @foreach(['handover' => 'Penyerahan', 'return' => 'Pengembalian'] as $stage => $stageLabel)
        @php($sessions = $booking->conditionSessions->where('stage', $stage)->sortBy('attempt'))
        @php($session = $sessions->last())
        @php($mine = $session?->reports->where('kind', 'initial')->where('user_id', auth()->id())->first())
        @php($stageAvailable = $inspectionActions && ($stage === 'handover' || $booking->handed_over_at))
        <article class="card p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="text-lg font-semibold">{{ $stageLabel }}</h3><span class="badge bg-zinc-100 text-zinc-700">{{ $session?->statusLabel() ?? 'Belum dimulai' }}</span></div>
            @if($booking->fulfillment_method === \App\Models\Booking::Delivery)<p class="mt-3 text-sm leading-6 text-zinc-600">{{ $stage === 'handover' ? 'Pemilik mengirim bukti kondisi dan kemasan sebelum penyewa mencatat kondisi saat paket diterima.' : 'Penyewa mengirim bukti saat pengiriman balik, lalu pemilik mencatat kondisi saat menerima kembali.' }} Waktu masing-masing dicatat terpisah.</p>@else<p class="mt-3 text-sm leading-6 text-zinc-600">Kedua pihak menyatakan siap. Sesi unggah bersama berlangsung 30 menit; persetujuan diberikan setelah bukti kedua pihak terkirim.</p>@endif
            @if($session?->bothSubmitted())
                <p class="mt-2 text-sm text-zinc-500">Bukti kedua pihak sudah terkirim. Hasil pemeriksaan mengikuti keputusan kedua pihak atau penyelesaian admin.</p>
            @elseif($session?->expires_at)
                <p class="mt-2 text-sm text-zinc-500">Batas unggah: {{ $session->expires_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB <span data-inspection-deadline="{{ $session->expires_at->toIso8601String() }}"></span>. Tidak ada persetujuan otomatis saat sesi berakhir.</p>
            @endif
            @if($isParty && $stageAvailable)
                @if(!$session || in_array($session->status, ['waiting', 'active', 'expired'], true) || $session->hasExpired())
                    <form method="POST" action="{{ route('inspections.store', [$booking, $stage]) }}" class="mt-4">@csrf<input type="hidden" name="action" value="ready">@if($session)<input type="hidden" name="session_id" value="{{ $session->id }}">@endif<x-button type="submit" variant="outline">{{ $session?->hasExpired() || $session?->status === 'expired' ? 'Siap untuk Sesi Baru' : 'Saya Siap Memeriksa' }}</x-button></form>
                @endif
                @if($session && $session->status === 'active' && !$session->hasExpired() && !$mine?->submitted_at)
                    <form method="POST" action="{{ route('inspections.store', [$booking, $stage]) }}" enctype="multipart/form-data" class="mt-5 grid gap-4 border-t border-zinc-200 pt-5" data-condition-upload>
                        @csrf<input type="hidden" name="session_id" value="{{ $session->id }}">
                        <label><span class="field-label">Hasil uji fungsi</span><select name="function_state" class="input"><option value="">Pilih hasil pemeriksaan</option>@foreach(['working' => 'Berfungsi', 'issues' => 'Ada masalah', 'untested' => 'Belum dapat diuji'] as $value => $label)<option value="{{ $value }}" @selected(old('function_state', $mine?->function_state) === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label><span class="field-label">Kelengkapan / aksesori</span><textarea name="accessories" rows="2" maxlength="2000" class="input" placeholder="Contoh: charger, tas, baterai; tulis jika ada yang kurang">{{ old('accessories', $mine?->accessories) }}</textarea></label>
                        <label><span class="field-label">Nomor seri (jika ada)</span><input name="serial_number" value="{{ old('serial_number', $mine?->serial_number) }}" maxlength="255" class="input"></label>
                        <label><span class="field-label">Kondisi dan cacat yang ditemukan</span><textarea name="condition_note" rows="3" maxlength="2000" class="input">{{ old('condition_note', $mine?->condition_note) }}</textarea></label>
                        <label><span class="field-label">Foto kondisi {{ $policy['photos_required'] ? '(wajib sebelum dikirim)' : '(opsional)' }}</span><input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="photo-file-input" data-condition-photos><span class="field-help">Total maksimal 5 foto pada bukti awal; JPG, PNG, atau WebP, masing-masing maksimal 5 MB. Dokumentasikan keseluruhan, bagian penting, cacat, dan aksesori. Jangan sertakan KTP/SIM.</span></label>
                        <div hidden data-condition-preview class="grid grid-cols-2 gap-3"></div><p hidden data-condition-error class="field-error" role="alert"></p>
                        @if($mine?->photos)
                            <div class="grid grid-cols-2 gap-3">@foreach($mine->photos as $photo)<img src="{{ route('inspections.photo', [$booking, $mine, $photo['id']]) }}" alt="Foto draf kondisi" class="h-36 w-full rounded-md border border-zinc-200 object-contain">@endforeach</div><label class="flex gap-2 text-sm"><input type="checkbox" name="replace_photos" value="1"> Ganti seluruh foto draf dengan pilihan baru</label>
                        @endif
                        @if($booking->fulfillment_method === \App\Models\Booking::Delivery && $isOwner)<label class="flex items-start gap-3 text-sm"><input type="checkbox" name="physical_confirmation" value="1" class="mt-1"><span>{{ $stage === 'handover' ? 'Barang sudah saya serahkan ke kurir. Pembatalan biasa tidak tersedia setelah dicatat.' : 'Barang sudah saya terima kembali secara fisik. Stok dapat dibebaskan meskipun kondisi masih diperiksa.' }}</span></label>@endif
                        <div class="flex flex-wrap gap-2"><button type="submit" name="action" value="save" class="btn btn-outline">Simpan Draf</button><button type="submit" name="action" value="send" class="btn btn-primary">Kirim &amp; Kunci Bukti</button></div>
                    </form>
                @endif
                @if($session)
                    <details class="mt-4 rounded-md border border-zinc-200 p-3"><summary class="cursor-pointer text-sm font-semibold">Bukti Tambahan / Bantuan Admin</summary><form method="POST" action="{{ route('inspections.store', [$booking, $stage]) }}" enctype="multipart/form-data" class="mt-3 grid gap-3" data-condition-upload>@csrf<input type="hidden" name="action" value="supplement"><input type="hidden" name="session_id" value="{{ $session->id }}"><label><span class="field-label">Penjelasan tambahan</span><textarea name="note" required maxlength="2000" rows="2" class="input"></textarea></label><input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="photo-file-input" data-condition-photos><div hidden data-condition-preview class="grid grid-cols-2 gap-3"></div><p hidden data-condition-error class="field-error" role="alert"></p>
@if(!$session->resolved_at)
<label class="flex items-start gap-3 text-sm"><input type="checkbox" name="request_admin_review" value="1" class="mt-1"><span>Minta pemeriksaan admin karena ada masalah atau pihak lain tidak kooperatif. Pencairan akan ditahan.</span></label>
@endif
<p class="text-xs text-zinc-500">Tambahan bukti tidak menggantikan bukti awal atau mengubah keputusan yang sudah dicatat.</p>
<x-button type="submit" class="w-fit" variant="outline">Kirim Tambahan Bukti</x-button></form></details>
                @endif
                @if($session?->bothSubmitted() && !$session->hasExpired() && $mine?->submitted_at && !$mine->decision && !$session->resolved_at)
                    <div class="mt-5 grid gap-3 sm:grid-cols-2"><form method="POST" action="{{ route('inspections.store', [$booking, $stage]) }}">@csrf<input type="hidden" name="session_id" value="{{ $session->id }}"><input type="hidden" name="action" value="agree"><x-button type="submit">Kondisi Sesuai</x-button></form><form method="POST" action="{{ route('inspections.store', [$booking, $stage]) }}" class="grid gap-2">@csrf<input type="hidden" name="session_id" value="{{ $session->id }}"><input type="hidden" name="action" value="dispute"><label><span class="field-label">Perbedaan yang ditemukan</span><textarea name="note" required maxlength="2000" rows="2" class="input"></textarea></label><x-button type="submit" variant="danger">Ada Perbedaan</x-button></form></div>
                @endif
                @if($isOwner && ($stage === 'return' ? !$booking->returned_at : !$booking->handed_over_at))
                    <form method="POST" action="{{ route('inspections.store', [$booking, $stage]) }}" class="mt-5 rounded-md bg-zinc-50 p-4">@csrf<input type="hidden" name="action" value="physical">@if($session)<input type="hidden" name="session_id" value="{{ $session->id }}">@endif<label class="flex items-start gap-3 text-sm leading-6"><input type="checkbox" name="physical_confirmation" value="1" required class="mt-1"><span>{{ $stage === 'return' ? 'Saya sudah menerima barang kembali secara fisik. Konfirmasi ini membebaskan stok, bukan menyetujui kondisi atau pencairan.' : 'Barang sudah saya serahkan secara fisik atau kepada kurir. Konfirmasi ini mengunci pembatalan, bukan menyatakan kedua pihak setuju dengan kondisi.' }}</span></label><x-button type="submit" variant="outline" class="mt-3">{{ $stage === 'return' ? 'Konfirmasi Penerimaan Fisik' : 'Catat Penyerahan Fisik' }}</x-button></form>
                @endif
            @elseif(!$stageAvailable)<p class="mt-4 text-sm text-zinc-500">{{ $inspectionActions ? 'Tersedia setelah penyerahan fisik dicatat.' : 'Tindakan tersedia setelah pembayaran lunas, sebelum pencairan diproses.' }}</p>@endif
            @foreach($sessions as $history)
                <details class="mt-5 border-t border-zinc-200 pt-4" @if($history->id === $session?->id) open @endif><summary class="cursor-pointer text-sm font-semibold">Sesi {{ $history->attempt }} · {{ $history->statusLabel() }}</summary>
                    <div class="mt-3 grid gap-3">
                    @foreach($history->reports as $report)
                        @if($report->user_id === auth()->id() || ($report->submitted_at && $history->canSeeOthers(auth()->user())))
                            <article class="rounded-md border border-zinc-200 p-4 text-sm"><strong>{{ match($report->role) {'owner' => 'Pemilik', 'renter' => 'Penyewa', default => 'Admin'} }} · {{ $report->kind === 'initial' ? 'Bukti awal' : ($report->kind === 'admin' ? 'Dasar keputusan' : 'Tambahan bukti') }}</strong><p class="mt-1 text-zinc-500">{{ $report->submitted_at ? $report->submitted_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i:s').' WIB' : 'Draf belum dikirim' }}</p><p class="mt-3">Fungsi: {{ match($report->function_state) {'working' => 'Berfungsi', 'issues' => 'Ada masalah', 'untested' => 'Belum dapat diuji', default => 'Belum dicatat'} }}</p><p class="mt-1 whitespace-pre-line">Aksesori: {{ $report->accessories ?: 'Belum dicatat' }}</p><p class="mt-1 break-words">Nomor seri: {{ $report->serial_number ?: 'Belum dicatat' }}</p><p class="mt-2 whitespace-pre-line">{{ $report->condition_note }}</p><div class="mt-3 grid grid-cols-2 gap-3">@foreach($report->photos ?? [] as $photo)<a href="{{ route('inspections.photo', [$booking, $report, $photo['id']]) }}" target="_blank" rel="noopener"><img src="{{ route('inspections.photo', [$booking, $report, $photo['id']]) }}" alt="Bukti kondisi {{ $report->role }}" class="h-40 w-full rounded-md border border-zinc-200 bg-white object-contain"></a>@endforeach</div><p class="mt-3 font-semibold">{{ match($report->decision) {'agree' => 'Kondisi sesuai', 'dispute' => 'Ada perbedaan kondisi', default => 'Belum memberi keputusan'} }}</p>@if($report->decision_note)<p class="mt-1 text-red-800">{{ $report->decision_note }}</p>@endif</article>
                        @endif
                    @endforeach
                    @if(!$history->canSeeOthers(auth()->user()))<p class="text-sm text-zinc-500">Dokumentasi pihak lain terbuka setelah kedua pihak mengirim bukti atau sesi berakhir.</p>@endif
                    @if($history->resolution_note)<p class="rounded-md bg-amber-50 p-3 text-sm"><strong>Keputusan admin:</strong> {{ $history->resolution_note }}. Ini bukan persetujuan atas nama pengguna.</p>@endif
                    <x-button :href="route('inspections.document', [$booking, $history])" variant="outline" class="w-fit">Unduh Berita Acara{{ $history->isSettled() ? '' : ' Belum Lengkap' }}</x-button>
                    </div>
                </details>
            @endforeach
            @can('resolveInspection', $booking)
                @if($stageAvailable)
                <details class="mt-5 rounded-md border border-amber-200 p-4">
                    <summary class="cursor-pointer text-sm font-semibold">Pemeriksaan Admin / Pihak Tidak Kooperatif</summary>
                    <form method="POST" action="{{ route('inspections.store', [$booking, $stage]) }}" enctype="multipart/form-data" class="mt-4 grid gap-3" data-condition-upload>
                        @csrf
                        <input type="hidden" name="action" value="resolve">
                        @if($session)<input type="hidden" name="session_id" value="{{ $session->id }}">@endif
                        <label><span class="field-label">Hasil pemeriksaan</span><select name="outcome" required class="input"><option value="confirmed">Konfirmasi pemeriksaan berdasarkan bukti</option><option value="financial_review">Tetap tahan dana; perlu penanganan keuangan</option></select></label>
                        <label><span class="field-label">Peristiwa fisik yang dibuktikan</span><select name="physical_event" class="input"><option value="none">Tidak mengubah catatan fisik</option><option value="handed_over">Barang telah diserahkan</option><option value="returned">Barang telah diterima kembali oleh pemilik</option></select></label>
                        <label><span class="field-label">Alasan dan dasar keputusan</span><textarea name="note" required maxlength="2000" rows="3" class="input"></textarea></label>
                        <label><span class="field-label">Pilih bukti foto booking yang sudah dikirim</span><select name="evidence_report_id" class="input"><option value="">Unggah bukti baru di bawah</option>
                            @foreach($booking->conditionSessions as $sourceSession)
                                @foreach($sourceSession->reports as $sourceReport)
                                    @if($sourceReport->submitted_at && $sourceReport->photos)
                                        <option value="{{ $sourceReport->id }}">#{{ $sourceReport->id }} · {{ $sourceSession->stage }} · {{ $sourceReport->role }}</option>
                                    @endif
                                @endforeach
                            @endforeach
                        </select></label>
                        <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="photo-file-input" data-condition-photos>
                        <div hidden data-condition-preview class="grid grid-cols-2 gap-3"></div><p hidden data-condition-error class="field-error" role="alert"></p>
                        <p class="text-xs leading-5 text-zinc-500">Wajib memakai bukti foto dari booking ini atau unggahan baru. Tidak mengubah nominal, menyetujui atas nama pengguna, atau otomatis melepas penahanan pencairan.</p>
                        <x-button type="submit" class="w-fit">Catat Keputusan Admin</x-button>
                    </form>
                </details>
                @endif
            @endcan
        </article>
    @endforeach
</section>
@endif
