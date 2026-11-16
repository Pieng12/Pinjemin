@extends('layouts.admin')

@section('title', 'Barang - Administrator Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="[
            'Administrator' => route('admin.dashboard'),
            'Barang' => null,
        ]" />

        <div class="page-header mt-6">
            <div>
                <p class="eyebrow">Moderasi katalog</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-zinc-950">Barang</h1>
                <p class="mt-2 text-zinc-600">Daftar barang dari seluruh pengguna Pinjemin.</p>
            </div>
        </div>

        @php
            $filterSelects = [
                'status' => ['label' => 'Status', 'all' => 'Semua status', 'options' => ['active' => 'Aktif', 'inactive' => 'Nonaktif']],
                'category' => ['label' => 'Kategori', 'all' => 'Semua kategori', 'options' => $categories->pluck('name', 'slug')->all()],
            ];
        @endphp
        <x-list-filters :action="route('admin.items.index')" :filters="$filters" search-label="Cari barang" placeholder="Nama barang atau pemilik" :selects="$filterSelects" />
        <x-list-results :results="$items" :total-count="$totalCount" :filters="$filters" :selects="$filterSelects" :action="route('admin.items.index')" empty-title="Belum ada barang" empty-description="Barang dari pengguna akan tampil di sini." />

        @if ($items->isNotEmpty())
            <div class="mt-8 table-shell">
                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm">
                        <thead class="table-head">
                            <tr>
                                <th class="px-4 py-3">Barang</th>
                                <th class="px-4 py-3">Pemilik</th>
                                <th class="px-4 py-3">Kategori</th>
                                <th class="px-4 py-3">Harga</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Dibuat</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @foreach ($items as $item)
                                <tr class="table-row">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <x-item-image :item="$item" class="h-14 max-w-20" />
                                            <span class="font-medium text-zinc-950">{{ $item->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-zinc-600">{{ $item->user->name }}</td>
                                    <td class="px-4 py-3 text-zinc-600">{{ $item->category->name }}</td>
                                    <td class="px-4 py-3"><x-price :amount="$item->daily_price" suffix="" /></td>
                                    <td class="px-4 py-3"><x-status-badge :item="$item" /></td>
                                    <td class="px-4 py-3 text-zinc-600">{{ $item->created_at->format('d M Y') }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <x-button href="{{ route('admin.items.show', $item) }}" variant="outline">Detail</x-button>
                                            @if ($item->is_active)
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.items.deactivate', $item) }}"
                                                    data-confirm
                                                    data-confirm-title="Nonaktifkan barang?"
                                                    data-confirm-message="Barang ini akan disembunyikan dari katalog aktif."
                                                    data-confirm-button="Nonaktifkan"
                                                >
                                                    @csrf
                                                    @method('PATCH')
                                                    <x-button type="submit" variant="danger">Nonaktifkan</x-button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="divide-y divide-zinc-200 lg:hidden">
                    @foreach ($items as $item)
                        <article class="space-y-4 p-4">
                            <div class="flex items-start gap-3">
                                <x-item-image :item="$item" class="h-20 max-w-24 shrink-0" />
                                <div class="min-w-0 space-y-2">
                                    <h2 class="break-words font-semibold text-zinc-950">{{ $item->name }}</h2>
                                    <x-status-badge :item="$item" />
                                    <x-price :amount="$item->daily_price" class="block font-semibold text-blue-700" />
                                </div>
                            </div>
                            <dl class="grid grid-cols-2 gap-3 text-sm">
                                <div class="min-w-0"><dt class="text-zinc-500">Pemilik</dt><dd class="break-words text-zinc-700">{{ $item->user->name }}</dd></div>
                                <div class="min-w-0"><dt class="text-zinc-500">Kategori</dt><dd class="break-words text-zinc-700">{{ $item->category->name }}</dd></div>
                                <div><dt class="text-zinc-500">Dibuat</dt><dd class="text-zinc-700">{{ $item->created_at->format('d M Y') }}</dd></div>
                            </dl>
                            <div class="flex flex-wrap gap-2">
                                <x-button :href="route('admin.items.show', $item)" variant="outline">Detail</x-button>
                                @if ($item->is_active)
                                    <form method="POST" action="{{ route('admin.items.deactivate', $item) }}" data-confirm data-confirm-title="Nonaktifkan barang?" data-confirm-message="Barang ini akan disembunyikan dari katalog aktif." data-confirm-button="Nonaktifkan">
                                        @csrf
                                        @method('PATCH')
                                        <x-button type="submit" variant="danger">Nonaktifkan</x-button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-6">{{ $items->links() }}</div>
    </section>
@endsection
