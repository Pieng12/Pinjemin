@extends('layouts.owner')

@section('title', 'Tambah Barang - Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="['Dasbor Pemilik' => route('owner.dashboard'), 'Barang Saya' => route('my-items.index'), 'Tambah Barang' => null]" />
        <x-owner-heading title="Tambah Barang" description="Isi rincian barang, tarif, dan cara pengambilan sebelum dipublikasikan." class="mt-4" />

        <form method="POST" action="{{ route('my-items.store') }}" enctype="multipart/form-data" class="mt-6 max-w-4xl">
            @csrf
            @include('my-items.partials.form')
            <div class="owner-form-actions">
                <x-button :href="route('my-items.index')" variant="outline">Batal</x-button>
                <x-button type="submit">Simpan Barang</x-button>
            </div>
        </form>
    </section>
@endsection
