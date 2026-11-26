@extends('layouts.owner')

@section('title', 'Barang Saya - Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="['Dasbor Pemilik' => route('owner.dashboard'), 'Barang Saya' => null]" />
        <x-owner-heading title="Barang Saya" class="mt-4">
            <x-button :href="route('my-items.create')"><i data-lucide="plus" class="size-4" aria-hidden="true"></i> Tambah Barang</x-button>
        </x-owner-heading>

        @php
            $filterSelects = [
                'status' => ['label' => 'Status', 'all' => 'Semua status', 'options' => ['active' => 'Aktif', 'inactive' => 'Nonaktif']],
                'category' => ['label' => 'Kategori', 'all' => 'Semua kategori', 'options' => $categories->pluck('name', 'slug')->all()],
            ];
        @endphp
        <x-list-filters compact class="owner-list-toolbar" :action="route('my-items.index')" :filters="$filters" search-label="Cari barang" placeholder="Nama barang" :selects="$filterSelects" />
        <x-list-results noun="barang" :results="$items" :total-count="$totalCount" :filters="$filters" :selects="$filterSelects" :action="route('my-items.index')" empty-title="Belum ada barang yang Anda sewakan." empty-description="Tambahkan barang pertama beserta foto, tarif, dan lokasi pengambilan." empty-action-label="Tambah Barang" :empty-action-url="route('my-items.create')" />

        @if ($items->isNotEmpty())
            <div class="owner-records mt-4">
                <div class="owner-record-labels owner-inventory-grid" aria-hidden="true">
                    <span>Barang</span><span>Status</span><span class="text-right">Tarif sewa</span><span class="text-right">Tindakan</span>
                </div>
                <ul class="divide-y divide-zinc-200">
                    @foreach ($items as $item)
                        <li class="owner-inventory-grid owner-record-row" data-owner-inventory-row>
                            <div class="owner-record-product flex min-w-0 items-center gap-3.5">
                                <x-item-image :item="$item" class="h-16 max-w-20 shrink-0" placeholder="Tanpa foto" />
                                <div class="min-w-0">
                                    <h2 class="font-semibold wrap-anywhere text-zinc-950"><a href="{{ route('my-items.show', $item) }}" class="hover:text-brand-700 hover:underline">{{ $item->name }}</a></h2>
                                    <p class="mt-1 text-sm text-zinc-600">{{ $item->category->name }} <span aria-hidden="true">·</span> {{ $item->quantity }} unit</p>
                                    <p class="mt-0.5 text-xs text-zinc-600">{{ $item->city }}</p>
                                </div>
                            </div>
                            <div class="owner-record-status"><x-status-badge :item="$item" class="owner-item-status" /></div>
                            <div class="owner-record-amount text-right font-semibold text-zinc-950 tabular-nums"><x-price :amount="$item->daily_price" /></div>
                            <div class="owner-record-actions owner-item-actions">
                                <x-button :href="route('my-items.show', $item)" variant="outline" :aria-label="'Lihat '.$item->name">Lihat</x-button>
                                <x-button :href="route('my-items.edit', $item)" variant="outline" :aria-label="'Edit '.$item->name">Edit</x-button>
                                <details name="owner-item-management" class="group relative min-w-0" data-owner-management>
                                    <summary class="btn btn-outline flex w-full cursor-pointer list-none items-center justify-center gap-1.5 px-2 group-open:border-brand-300 group-open:bg-brand-50">
                                        Kelola <i data-lucide="chevron-down" class="size-3.5 shrink-0 group-open:rotate-180" aria-hidden="true"></i>
                                    </summary>
                                    <div class="owner-item-menu">
                                        <form method="POST" action="{{ route('my-items.requirements', $item) }}" class="grid gap-3">
                                            @csrf
                                            @method('PATCH')
                                            <fieldset class="grid gap-2.5">
                                                <legend class="mb-3 font-semibold text-zinc-950">Persyaratan Foto &amp; Identitas</legend>
                                                <input type="hidden" name="requires_condition_photos" value="0">
                                                <label class="flex min-h-9 items-start gap-2.5"><input class="mt-1 size-4 shrink-0 accent-brand-700" type="checkbox" name="requires_condition_photos" value="1" @checked($item->requires_condition_photos)> Wajibkan foto kondisi dari kedua pihak</label>
                                                <input type="hidden" name="requires_identity_verification" value="0">
                                                <label class="flex min-h-9 items-start gap-2.5"><input class="mt-1 size-4 shrink-0 accent-brand-700" type="checkbox" name="requires_identity_verification" value="1" @checked($item->requires_identity_verification)> Wajibkan verifikasi identitas penyewa</label>
                                            </fieldset>
                                            <p class="text-xs text-zinc-600">Berlaku untuk pemesanan berikutnya.</p>
                                            <x-button type="submit" variant="outline" class="w-full">Simpan Persyaratan</x-button>
                                        </form>
                                        <div class="mt-4 grid grid-cols-2 gap-2 border-t border-zinc-200 pt-3">
                                            <form method="POST" action="{{ route('my-items.toggle-status', $item) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-button type="submit" variant="outline" class="w-full px-2">{{ $item->is_active && $item->status === \App\ItemStatus::Available ? 'Nonaktifkan' : 'Aktifkan' }}</x-button>
                                            </form>
                                            <form method="POST" action="{{ route('my-items.destroy', $item) }}" data-confirm data-confirm-title="Hapus barang?" data-confirm-description="Barang dapat dihapus jika belum memiliki riwayat pemesanan.">
                                                @csrf
                                                @method('DELETE')
                                                <x-button type="submit" variant="danger" class="w-full px-2">Hapus</x-button>
                                            </form>
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="mt-5">{{ $items->links() }}</div>
        @endif
    </section>
@endsection
