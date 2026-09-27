@extends('layouts.owner')

@section('title', 'Tambah Barang - Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="['Dashboard Pemilik' => route('owner.dashboard'), 'Barang Saya' => route('my-items.index'), 'Tambah Barang' => null]" />
        <div class="page-header">
            <div>
                <h1 class="text-3xl font-semibold text-zinc-950">Tambahkan Barang</h1>
                <p class="mt-2 max-w-2xl text-zinc-600">Lengkapi informasi barang agar penyewa memahami barang yang kamu tawarkan.</p>
            </div>
        </div>

        <div class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,1fr)_280px]">
            <form method="POST" action="{{ route('my-items.store') }}" enctype="multipart/form-data">
                @csrf
                @include('my-items.partials.form')
                <div class="mt-6 flex flex-wrap gap-3">
                    <x-button type="submit">Simpan Barang</x-button>
                    <x-button :href="route('my-items.index')" variant="outline">Batal</x-button>
                </div>
            </form>

            <aside class="card card-pad h-fit xl:sticky xl:top-24">
                <h2 class="font-semibold text-zinc-950">Tips listing yang baik</h2>
                <ul class="mt-4 grid gap-3 text-sm leading-6 text-zinc-600">
                    <li>Gunakan foto yang jelas dan terang.</li>
                    <li>Tulis kondisi barang apa adanya.</li>
                    <li>Tentukan harga yang sesuai dengan pasar lokal.</li>
                    <li>Jelaskan aturan penggunaan agar penyewa tidak bingung.</li>
                </ul>
            </aside>
        </div>
    </section>
@endsection
