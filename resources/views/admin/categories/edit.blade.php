@extends('layouts.admin')

@section('title', 'Edit Kategori - Admin Pinjemin')

@section('content')
    <section class="page-shell max-w-3xl">
        <x-breadcrumb :items="[
            'Admin' => route('admin.dashboard'),
            'Kategori' => route('admin.categories.index'),
            'Edit' => null,
        ]" />

        <div class="card card-pad mt-6">
            <p class="eyebrow">Master data</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-zinc-950">Edit kategori</h1>
            <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="mt-6 grid gap-5">
                @csrf
                @method('PUT')
                @include('admin.categories.partials.form', ['category' => $category])
                <div class="flex flex-wrap gap-3">
                    <x-button type="submit">Simpan perubahan</x-button>
                    <x-button href="{{ route('admin.categories.index') }}" variant="outline">Batal</x-button>
                </div>
            </form>
        </div>
    </section>
@endsection
