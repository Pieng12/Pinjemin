@extends('layouts.app')
@section('title', 'Verifikasi Identitas - Pinjemin')
@section('content')
<section class="page-shell max-w-3xl">
    <div class="page-header"><div><p class="eyebrow">Privat melalui admin</p><h1 class="mt-2 text-3xl font-semibold">Verifikasi Identitas</h1><p class="mt-3 text-sm leading-6 text-zinc-600">Diperlukan jika pemilik barang mensyaratkannya. Pemeriksaan manual berlaku maksimal satu tahun; bukan validasi Dukcapil atau jaminan keaslian identitas.</p></div></div>
    <div class="card mt-6 p-5"><p class="text-sm text-zinc-500">Status akun</p><p class="mt-1 font-semibold text-brand">{{ $current?->statusLabel() ?? 'Belum Diajukan' }}</p>@if($current)<a href="{{ route('identity.show', $current) }}" class="mt-3 inline-block text-sm font-semibold text-brand underline">Lihat pengajuan dan keputusan</a>@endif</div>
    @if(!$current || (!$current->isValid() && $current->status !== 'pending'))
    <form method="POST" action="{{ route('identity.store') }}" enctype="multipart/form-data" class="card mt-6 grid gap-5 p-5 sm:p-6" data-private-image-upload>
        @csrf
        <h2 class="text-lg font-semibold">Ajukan Identitas</h2>
        <div class="rounded-md bg-zinc-50 p-4 text-sm leading-6 text-zinc-600">Dokumen dan nomor lengkap disimpan terenkripsi, hanya untukmu dan admin berwenang. Pemilik barang hanya melihat status. Digunakan untuk pemeriksaan identitas dan penanganan pengaduan sesuai dasar yang sah. Dokumen disimpan selama masa verifikasi maksimal satu tahun; penghapusan ditunda jika ada booking atau sengketa aktif, dengan peninjauan setiap 30 hari. Pengajuan ditolak/ditarik dihapus setelah 30 hari jika tidak terkait kasus. Jangan unggah identitas pada foto kondisi barang.</div>
        <label><span class="field-label">Dokumen</span><select name="document_type" class="input" required><option value="ktp" @selected(old('document_type') === 'ktp')>KTP</option><option value="sim" @selected(old('document_type') === 'sim')>SIM</option></select></label>
        <label><span class="field-label">Nama sesuai dokumen</span><input name="legal_name" required maxlength="255" autocomplete="name" class="input"><span class="field-help">Boleh berbeda dari nama panggilan akun.</span></label>
        <label><span class="field-label">Nomor identitas</span><input name="document_number" required maxlength="50" inputmode="numeric" autocomplete="off" class="input"><span class="field-help">KTP: 16 angka. Nomor tidak dipulihkan otomatis jika validasi gagal.</span></label>
        <label><span class="field-label">Tanggal kedaluwarsa (wajib untuk SIM)</span><input type="date" name="document_expires_on" value="{{ old('document_expires_on') }}" min="{{ now(\App\Models\Booking::RentalTimezone)->toDateString() }}" class="input"></label>
        <label><span class="field-label">Foto dokumen</span><input type="file" name="document" accept="image/jpeg,image/png,image/webp" required class="photo-file-input" data-private-image-input><span class="field-help">Satu foto JPG, PNG, atau WebP maksimal 5 MB. Pastikan tulisan terbaca.</span></label>
        <div data-private-image-preview hidden><img alt="Preview identitas privat" class="max-h-80 w-full rounded-md border border-zinc-200 bg-white object-contain p-2"></div>
        <label class="flex items-start gap-3 text-sm leading-6"><input type="checkbox" name="consent" value="1" required class="mt-1 h-4 w-4 accent-brand"><span>Saya menyetujui pemrosesan data identitas untuk tujuan dan masa penyimpanan yang dijelaskan di atas.</span></label>
        <x-button type="submit" class="w-fit">Kirim ke Admin</x-button>
    </form>
    @endif
    <div class="mt-7 grid gap-3"><h2 class="text-lg font-semibold">Riwayat Pengajuan</h2>@forelse($verifications as $verification)<a href="{{ route('identity.show', $verification) }}" class="card flex flex-wrap justify-between gap-3 p-4 text-sm"><span>{{ strtoupper($verification->document_type) }} · {{ $verification->created_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB</span><strong>{{ $verification->statusLabel() }}</strong></a>@empty<p class="text-sm text-zinc-500">Belum ada pengajuan.</p>@endforelse</div>
</section>
@endsection
