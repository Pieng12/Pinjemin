@extends('layouts.owner')

@section('title', $item->name.' - Barang Saya')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="[
            'Dashboard Pemilik' => route('owner.dashboard'),
            'Barang Saya' => route('my-items.index'),
            $item->name => null,
        ]" />

        <div class="page-header mt-6">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-status-badge :item="$item" />
                    <x-condition-badge :condition="$item->condition" />
                </div>
                <h1 class="mt-3 text-3xl font-semibold text-zinc-950">{{ $item->name }}</h1>
                <p class="mt-2 text-zinc-600">{{ $item->category->name }} / {{ $item->city }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <x-button href="{{ route('items.show', $item) }}" variant="outline">Preview publik</x-button>
                <x-button href="{{ route('my-items.edit', $item) }}">Edit Barang</x-button>
            </div>
        </div>

        <div class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <div class="space-y-4">
                <x-item-image :item="$item" class="owner-detail-photo" />
                <div class="grid grid-cols-3 gap-3">
                    @forelse ($item->photos as $photo)
                        <a href="{{ $photo->url() }}" target="_blank" rel="noopener" aria-label="Buka foto {{ $item->name }}"><img src="{{ $photo->url() }}" alt="{{ $item->name }}" class="aspect-[4/3] w-full rounded-md bg-zinc-50 object-contain" loading="lazy"></a>
                    @empty
                        <div class="col-span-3 rounded-md border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500">Foto belum tersedia.</div>
                    @endforelse
                </div>
            </div>

            <div class="card card-pad">
                <x-price :amount="$item->daily_price" class="text-2xl font-bold text-blue-700" />
                <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="font-medium text-zinc-500">Jumlah unit</dt><dd class="mt-1 text-zinc-950">{{ $item->quantity }}</dd></div>
                    <div>
                        <dt class="font-medium text-zinc-500">DP per unit</dt>
                        <dd class="mt-1 text-zinc-950">
                            @if ($item->deposit_amount)
                                <x-price :amount="$item->deposit_amount" suffix="" />
                            @else
                                Tidak ada
                            @endif
                        </dd>
                    </div>
                    <div><dt class="font-medium text-zinc-500">Alamat</dt><dd class="mt-1 text-zinc-950">{{ $item->address ?? '-' }}</dd></div>
                    <div><dt class="font-medium text-zinc-500">Dibuat</dt><dd class="mt-1 text-zinc-950">{{ $item->created_at->format('d M Y') }}</dd></div>
                </dl>
                <div class="mt-6 space-y-5 text-sm leading-7 text-zinc-700">
                    <div>
                        <h2 class="font-semibold text-zinc-950">Deskripsi</h2>
                        <p class="mt-1 whitespace-pre-line">{{ $item->description }}</p>
                    </div>
                    @if ($item->rules)
                        <div>
                            <h2 class="font-semibold text-zinc-950">Aturan penyewaan</h2>
                            <p class="mt-1 whitespace-pre-line">{{ $item->rules }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
