@extends('layouts.app')

@section('title', 'Sewakan Barang - Pinjemin')
@section('nav_title', 'Sewakan Barang')
@section('meta_description', 'Mulai sewakan barang yang jarang digunakan di Pinjemin.')

@section('content')
    <section class="page-shell rent-out-page">
        @auth
            <div class="page-header marketplace-reveal" data-reveal>
                <div>
                    <p class="eyebrow">Untuk pemilik barang</p>
                    <h1 class="mt-2 text-2xl font-semibold leading-tight sm:text-3xl">Barang yang kamu sewakan</h1>
                    <p class="mt-3 text-sm leading-6 text-zinc-600"><span class="font-semibold text-zinc-900">{{ $itemsCount }} barang terdaftar.</span> Kelola informasi dan permintaan sewamu dari sini.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-button :href="route('owner.dashboard')" variant="outline">Dashboard Pemilik <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i></x-button>
                    <x-button :href="route('my-items.create')"><i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i> Tambah Barang</x-button>
                </div>
            </div>

            <div class="mt-7 border-t border-zinc-200 pt-5" id="barang-saya">
                @if ($ownedItems?->count())
                    <p class="mb-5 text-sm text-zinc-600">Menampilkan <span class="font-semibold text-zinc-900">{{ $ownedItems->firstItem() }}-{{ $ownedItems->lastItem() }}</span> dari {{ $ownedItems->total() }} barang</p>
                    <div class="grid gap-5 lg:grid-cols-2">
                        @foreach ($ownedItems as $item)
                            <article class="owned-item-card marketplace-reveal group" data-reveal style="--reveal-delay: {{ min($loop->index * 40, 200) }}ms">
                                <a href="{{ route('my-items.show', $item) }}" class="block min-w-0 self-start" aria-label="Lihat {{ $item->name }}">
                                    <x-item-image :item="$item" fit="contain" class="aspect-[4/3]" />
                                </a>
                                <div class="flex min-w-0 flex-col">
                                    <p class="text-xs text-zinc-500">{{ $item->category->name }}</p>
                                    <h2 class="mt-1 line-clamp-2 text-base font-semibold leading-6"><a href="{{ route('my-items.show', $item) }}" class="hover:text-blue-700">{{ $item->name }}</a></h2>
                                    <p class="mt-1 text-sm text-zinc-500">{{ $item->city }}</p>
                                    <div class="mt-3 flex flex-wrap items-center gap-2"><x-status-badge :item="$item" /><span class="text-xs text-zinc-500">{{ $item->quantity }} unit</span></div>
                                    <p class="mt-3 text-sm"><x-price :amount="$item->daily_price" suffix="" class="font-semibold text-blue-700" /><span class="text-xs text-zinc-500"> / hari</span></p>
                                    <div class="mt-4 flex flex-wrap gap-2 border-t border-zinc-100 pt-3">
                                        <x-button :href="route('my-items.show', $item)" variant="outline" class="min-h-10">Lihat</x-button>
                                        <x-button :href="route('my-items.edit', $item)" variant="outline" class="min-h-10"><i data-lucide="pencil" class="h-3.5 w-3.5" aria-hidden="true"></i> Edit</x-button>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <div class="mt-8">{{ $ownedItems->links() }}</div>
                @else
                    <x-empty-state :show-mark="false" title="Belum ada barang yang kamu sewakan." description="Tambahkan foto dan informasi barang pertamamu untuk mulai menyewakan." action-label="Tambah Barang" :action-url="route('my-items.create')" />
                @endif
            </div>
        @else
            <div class="grid gap-8 pb-2 md:grid-cols-2 md:items-center lg:gap-16">
                <div class="marketplace-reveal" data-reveal>
                    <p class="eyebrow">Untuk pemilik barang</p>
                    <h1 class="mt-3 max-w-lg text-3xl font-semibold leading-tight sm:text-4xl">Barang jarang dipakai?<br>Beri manfaat baru.</h1>
                    <p class="mt-4 max-w-md text-sm leading-7 text-zinc-600">Sewakan barangmu, tentukan harga harian, dan kelola permintaan dari satu tempat.</p>
                    <div class="mt-6 flex flex-wrap gap-3"><x-button :href="route('register')">Mulai Sewakan <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i></x-button><x-button :href="route('login')" variant="outline">Masuk</x-button></div>
                </div>
                <div class="grid grid-cols-2 items-center gap-3" aria-label="Contoh perlengkapan untuk disewakan">
                    <img src="{{ asset('images/landing/hero-camera.jpg') }}" alt="Kamera sebagai contoh barang sewa" class="aspect-[4/5] w-full rounded-lg bg-zinc-100 object-contain" width="400" height="500">
                    <div class="grid gap-3"><img src="{{ asset('images/landing/tent.jpg') }}" alt="Perlengkapan camping" class="aspect-[4/3] w-full rounded-lg object-cover" width="400" height="300"><img src="{{ asset('images/landing/tools.jpg') }}" alt="Perkakas untuk proyek sementara" class="aspect-[4/3] w-full rounded-lg object-cover" width="400" height="300"></div>
                </div>
            </div>
        @endauth

        <section class="mt-10 border-t border-zinc-200 pt-8 sm:mt-12" aria-labelledby="owner-guide-title">
            <div class="marketplace-reveal max-w-xl" data-reveal><p class="eyebrow">Cara menyewakan barang</p><h2 id="owner-guide-title" class="mt-2 text-xl font-semibold sm:text-2xl">Mulai dari barang yang kamu punya.</h2></div>
            <ol class="mt-6 grid gap-6 md:grid-cols-3">
                @foreach ([['Tambahkan barang', 'Unggah foto asli dan informasi kondisi barang yang jelas.'], ['Tentukan harga dan aturan', 'Isi harga harian, DP, jumlah unit, dan aturan penyewaan.'], ['Kelola permintaan sewa', 'Tinjau booking masuk melalui Dashboard Pemilik.']] as [$title, $description])
                    <li class="marketplace-reveal border-t-2 border-blue-100 pt-4" data-reveal style="--reveal-delay: {{ $loop->index * 40 }}ms"><span class="text-sm font-semibold text-blue-700">0{{ $loop->iteration }}</span><h3 class="mt-3 text-base font-semibold">{{ $title }}</h3><p class="mt-2 text-sm leading-7 text-zinc-600">{{ $description }}</p></li>
                @endforeach
            </ol>
        </section>
    </section>
@endsection
