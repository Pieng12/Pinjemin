@extends('layouts.app')
@section('title', 'Verifikasi Identitas - Pinjemin')
@section('content')
<section class="page-shell max-w-3xl">
    <div class="page-header"><div><p class="eyebrow">Privat melalui administrator</p><h1 class="mt-2 text-3xl font-semibold">Verifikasi Identitas</h1><p class="mt-3 text-sm leading-6 text-zinc-600">Diperlukan jika pemilik barang mensyaratkannya. Pemeriksaan manual berlaku maksimal satu tahun; bukan validasi Dukcapil atau jaminan keaslian identitas.</p></div></div>
    <div class="card mt-6 p-5">
        <p class="text-sm text-zinc-500">Status akun</p>
        <div class="mt-1"><span class="badge bg-brand-50 text-brand-800">{{ $current?->statusLabel() ?? 'Belum Diajukan' }}</span></div>
        @if($current)<a href="{{ route('identity.show', $current) }}" class="mt-3 inline-block text-sm font-semibold text-brand underline">Lihat pengajuan dan keputusan</a>@endif
    </div>
    @if(!$current || (!$current->isValid() && $current->status !== 'pending'))
    <form id="identity-form" data-form-id="identity-form" method="POST" action="{{ route('identity.store') }}" enctype="multipart/form-data" class="card mt-6 grid gap-5 p-5 sm:p-6" data-private-image-upload>
        @csrf
        <x-validation-summary bag="default" form-id="identity-form" :field-ids="collect(['document_type', 'legal_name', 'document_number', 'document_expires_on', 'document', 'consent'])->mapWithKeys(fn ($name) => [$name => 'identity-'.$name])->all()" />
        <h2 class="text-lg font-semibold">Ajukan Identitas</h2>
        <div class="rounded-md bg-zinc-50 p-4 text-sm leading-6 text-zinc-600">Dokumen dan nomor lengkap disimpan terenkripsi, hanya untuk Anda dan administrator berwenang. Pemilik barang hanya melihat status. Digunakan untuk pemeriksaan identitas dan penanganan pengaduan sesuai dasar yang sah. Dokumen disimpan selama masa verifikasi maksimal satu tahun; penghapusan ditunda jika ada pemesanan atau sengketa aktif, dengan peninjauan setiap 30 hari. Pengajuan ditolak/ditarik dihapus setelah 30 hari jika tidak terkait kasus. Jangan unggah identitas pada foto kondisi barang.</div>
        <label for="identity-document_type"><span class="field-label">Dokumen</span><select id="identity-document_type" name="document_type" class="input" required data-identity-document-type aria-invalid="{{ $errors->has('document_type') ? 'true' : 'false' }}" @error('document_type') aria-describedby="identity-document_type-error" @enderror><option value="ktp" @selected(old('document_type') === 'ktp')>KTP</option><option value="sim" @selected(old('document_type') === 'sim')>SIM</option></select>@error('document_type')<span id="identity-document_type-error" data-error-field="document_type" class="field-error">{{ $message }}</span>@enderror</label>
        <label for="identity-legal_name"><span class="field-label">Nama sesuai dokumen</span><input id="identity-legal_name" name="legal_name" required maxlength="255" autocomplete="name" class="input" aria-invalid="{{ $errors->has('legal_name') ? 'true' : 'false' }}" aria-describedby="identity-legal_name-help @error('legal_name') identity-legal_name-error @enderror"><span id="identity-legal_name-help" class="field-help">Boleh berbeda dari nama panggilan akun. Isi kembali jika validasi gagal.</span>@error('legal_name')<span id="identity-legal_name-error" data-error-field="legal_name" class="field-error">{{ $message }}</span>@enderror</label>
        <label for="identity-document_number"><span class="field-label">Nomor identitas</span><input id="identity-document_number" name="document_number" required maxlength="50" inputmode="numeric" autocomplete="off" class="input" aria-invalid="{{ $errors->has('document_number') ? 'true' : 'false' }}" aria-describedby="identity-document_number-help @error('document_number') identity-document_number-error @enderror"><span id="identity-document_number-help" class="field-help">KTP: 16 angka. Nomor tidak dipulihkan otomatis jika validasi gagal.</span>@error('document_number')<span id="identity-document_number-error" data-error-field="document_number" class="field-error">{{ $message }}</span>@enderror</label>
        <label for="identity-document_expires_on"><span class="field-label">Tanggal kedaluwarsa (wajib untuk SIM)</span><input id="identity-document_expires_on" type="date" name="document_expires_on" value="{{ old('document_expires_on') }}" min="{{ now(\App\Models\Booking::RentalTimezone)->toDateString() }}" class="input" data-identity-expiration aria-invalid="{{ $errors->has('document_expires_on') ? 'true' : 'false' }}" aria-describedby="identity-document_expires_on-help @error('document_expires_on') identity-document_expires_on-error @enderror"><span id="identity-document_expires_on-help" class="field-help">SIM wajib memiliki tanggal kedaluwarsa. Untuk KTP seumur hidup, kosongkan tanggal ini.</span>@error('document_expires_on')<span id="identity-document_expires_on-error" data-error-field="document_expires_on" class="field-error">{{ $message }}</span>@enderror</label>
        <label for="identity-document"><span class="field-label">Foto dokumen</span><input id="identity-document" type="file" name="document" accept="image/jpeg,image/png,image/webp" required class="photo-file-input" data-private-image-input aria-invalid="{{ $errors->has('document') ? 'true' : 'false' }}" aria-describedby="identity-document-help @error('document') identity-document-error @enderror"><span id="identity-document-help" class="field-help">Satu foto JPG, PNG, atau WebP maksimal 5 MB. Pastikan tulisan terbaca.</span>@error('document')<span id="identity-document-error" data-error-field="document" class="field-error">{{ $message }}</span>@enderror</label>
        <div data-private-image-preview hidden><img alt="Pratinjau identitas privat" class="max-h-80 w-full rounded-md border border-zinc-200 bg-white object-contain p-2"></div>
        <label for="identity-consent" class="flex items-start gap-3 text-sm leading-6"><input id="identity-consent" type="checkbox" name="consent" value="1" required @checked(old('consent')) class="mt-1 h-4 w-4 accent-brand" aria-invalid="{{ $errors->has('consent') ? 'true' : 'false' }}" @error('consent') aria-describedby="identity-consent-error" @enderror><span>Saya menyetujui pemrosesan data identitas untuk tujuan dan masa penyimpanan yang dijelaskan di atas serta dalam <a href="{{ route('legal.privacy') }}#identitas" target="_blank" rel="noopener noreferrer" class="font-semibold text-blue-700 underline underline-offset-2">Kebijakan Privasi</a>.</span></label>
        @error('consent')<p id="identity-consent-error" data-error-field="consent" class="field-error" role="alert">{{ $message }}</p>@enderror
        <x-button type="submit" class="w-fit">Kirim ke Administrator</x-button>
    </form>
    @endif
    <div class="mt-7 grid gap-3">
        <h2 class="text-lg font-semibold">Riwayat Pengajuan</h2>
        @forelse($verifications as $verification)
            <a href="{{ route('identity.show', $verification) }}" class="card flex flex-wrap items-center justify-between gap-3 p-4 text-sm">
                <span>{{ strtoupper($verification->document_type) }} · {{ $verification->created_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB</span>
                <span class="badge bg-brand-50 text-brand-800">{{ $verification->statusLabel() }}</span>
            </a>
        @empty
            <p class="text-sm text-zinc-500">Belum ada pengajuan.</p>
        @endforelse
    </div>
</section>
@endsection
