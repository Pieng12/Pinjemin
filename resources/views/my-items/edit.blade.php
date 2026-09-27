@extends('layouts.owner')

@section('title', 'Edit Barang - Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="['Dashboard Pemilik' => route('owner.dashboard'), 'Barang Saya' => route('my-items.index'), $item->name => route('my-items.show', $item), 'Edit' => null]" />
        <div class="page-header mt-5">
            <div>
                <h1 class="text-3xl font-semibold text-zinc-950">Edit Barang</h1>
                <p class="mt-2 max-w-2xl text-zinc-600">Perbarui informasi listing, foto, harga, dan status publikasi barang.</p>
            </div>
        </div>

        <div class="mt-8">
            @if ($item->photos->count())
                <div class="card card-pad">
                    <h2 class="font-semibold text-zinc-950">Foto barang</h2>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($item->photos as $photo)
                            <div class="min-w-0">
                                <img src="{{ $photo->url() }}" alt="{{ $item->name }}" class="item-photo-natural max-h-60" loading="lazy">
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @if ($photo->is_primary)
                                        <span class="badge bg-emerald-50 text-emerald-700">Foto utama</span>
                                    @else
                                        <form method="POST" action="{{ route('my-items.photos.primary', [$item, $photo]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-outline min-h-9 px-3 py-1.5 text-xs">Jadikan utama</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('my-items.photos.destroy', [$item, $photo]) }}" data-confirm data-confirm-title="Hapus foto?" data-confirm-description="Foto ini akan dihapus dari barang.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger min-h-9 px-3 py-1.5 text-xs">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('my-items.update', $item) }}" enctype="multipart/form-data" class="mt-8">
                @csrf
                @method('PUT')
                @include('my-items.partials.form')
                <div class="mt-6 flex flex-wrap gap-3">
                    <x-button type="submit">Simpan Perubahan</x-button>
                    <x-button :href="route('my-items.show', $item)" variant="outline">Batal</x-button>
                </div>
            </form>
        </div>
    </section>
@endsection
