@extends('layouts.owner')

@section('title', $item->name.' - Barang Saya')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="[
            'Dasbor Pemilik' => route('owner.dashboard'),
            'Barang Saya' => route('my-items.index'),
            $item->name => null,
        ]" />

        <x-owner-heading :title="$item->name" :description="$item->category->name.' · '.$item->city" class="mt-4">
            <x-button :href="route('items.show', $item)" variant="outline">Pratinjau Katalog</x-button>
            <x-button :href="route('my-items.edit', $item)">Edit Barang</x-button>
        </x-owner-heading>

        <div class="mt-6 grid items-start gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <div class="space-y-4">
                <x-item-image :item="$item" class="owner-detail-photo" />
                @if ($item->photos->isNotEmpty())
                    <div class="grid grid-cols-3 gap-3">
                    @foreach ($item->photos as $photo)
                        <a href="{{ $photo->url() }}" target="_blank" rel="noopener" aria-label="Buka foto {{ $item->name }}"><img src="{{ $photo->url() }}" alt="{{ $item->name }}" class="aspect-[4/3] w-full rounded-md bg-zinc-50 object-contain" loading="lazy"></a>
                    @endforeach
                    </div>
                @endif
            </div>

            <div class="card card-pad">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><p class="text-sm font-medium text-zinc-600">Tarif sewa</p><x-price :amount="$item->daily_price" class="mt-1 block text-xl font-semibold text-brand-800" /></div>
                    <div class="flex flex-wrap items-center gap-2"><x-status-badge :item="$item" /><x-condition-badge :condition="$item->condition" /></div>
                </div>
                <dl class="mt-5 grid gap-4 border-t border-zinc-200 pt-4 text-sm sm:grid-cols-2">
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
                    <div><dt class="font-medium text-zinc-500">Dibuat</dt><dd class="mt-1 text-zinc-950">{{ $item->created_at->locale('id')->translatedFormat('d M Y') }}</dd></div>
                </dl>
                <div class="mt-5 space-y-4 border-t border-zinc-200 pt-4 text-sm leading-7 text-zinc-700">
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
