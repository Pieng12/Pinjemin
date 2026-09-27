@extends('layouts.owner')

@section('title', 'Metode Pembayaran - Pinjemin')

@section('content')
    <section class="page-shell max-w-6xl">
        <div class="page-header">
            <div>
                <p class="eyebrow">Pembayaran manual</p>
                <h1 class="mt-2 text-3xl font-semibold text-zinc-950">Metode Pembayaran</h1>
                <p class="mt-2 max-w-2xl text-zinc-600">Rekening hanya ditampilkan kepada penyewa setelah booking diterima.</p>
            </div>
            <p class="text-sm font-semibold text-zinc-600">{{ $activeMethodsCount }} / 10 metode aktif</p>
        </div>

        <div class="mt-8 grid gap-4 lg:grid-cols-2">
            @foreach($methods as $method)
                <article class="card card-pad">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold text-zinc-950">{{ $method->displayName() }}</h2>
                                @if($method->is_primary)<span class="badge bg-blue-50 text-blue-700">Utama</span>@endif
                                <span class="badge {{ $method->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-600' }}">{{ $method->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                            <p class="mt-2 text-sm text-zinc-600">{{ $method->account_name }}</p>
                            @if($method->account_identifier)<p class="mt-1 break-all font-mono text-sm text-zinc-900">{{ $method->account_identifier }}</p>@endif
                        </div>
                        @if($method->qris_path)
                            <img src="{{ route('payment-methods.image', $method) }}" alt="QRIS {{ $method->account_name }}" class="h-24 w-24 shrink-0 rounded-md border border-zinc-200 bg-white object-contain p-1">
                        @endif
                    </div>
                    @if($method->instructions)<p class="mt-4 whitespace-pre-line text-sm leading-6 text-zinc-600">{{ $method->instructions }}</p>@endif
                    <details class="mt-5 border-t border-zinc-100 pt-4">
                        <summary class="cursor-pointer text-sm font-semibold text-blue-700">Edit metode</summary>
                        <form method="POST" action="{{ route('payment-methods.update', $method) }}" enctype="multipart/form-data" class="mt-4 grid gap-4" data-payment-method-form data-private-image-upload>
                            @csrf @method('PUT')
                            @include('payment-methods.partials.fields', ['current' => $method])
                            <div class="flex flex-wrap gap-2"><x-button type="submit">Simpan</x-button></div>
                        </form>
                        <form method="POST" action="{{ route('payment-methods.destroy', $method) }}" class="mt-3" data-confirm data-confirm-title="Hapus metode pembayaran?" data-confirm-message="Metode hilang dari profil, tetapi snapshot booking lama tetap tersimpan." data-confirm-button="Hapus Metode">
                            @csrf @method('DELETE')
                            <x-button type="submit" variant="danger">Hapus</x-button>
                        </form>
                    </details>
                </article>
            @endforeach
        </div>

        <div class="card card-pad mt-8">
            <h2 class="text-lg font-semibold text-zinc-950">Tambah Metode</h2>
            <form method="POST" action="{{ route('payment-methods.store') }}" enctype="multipart/form-data" class="mt-5 grid gap-4" data-payment-method-form data-private-image-upload>
                @csrf
                @include('payment-methods.partials.fields', ['current' => null])
                <x-button type="submit" class="w-fit">Tambah Metode</x-button>
            </form>
        </div>
    </section>
@endsection
