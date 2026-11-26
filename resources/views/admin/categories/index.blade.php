@extends('layouts.admin')

@section('title', 'Kategori - Administrator Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="[
            'Administrator' => route('admin.dashboard'),
            'Kategori' => null,
        ]" />

        <div class="page-header mt-6">
            <div>
                <p class="eyebrow">Master data</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-zinc-950">Kategori</h1>
                <p class="mt-2 text-zinc-600">Master kategori barang Pinjemin.</p>
            </div>
            <x-button href="{{ route('admin.categories.create') }}">Tambah kategori</x-button>
        </div>

        @php
            $filterSelects = ['status' => ['label' => 'Status', 'all' => 'Semua status', 'options' => ['active' => 'Aktif', 'inactive' => 'Nonaktif']]];
        @endphp
        <x-list-filters :action="route('admin.categories.index')" :filters="$filters" search-label="Cari kategori" placeholder="Nama atau slug kategori" :selects="$filterSelects" />
        <x-list-results :results="$categories" :total-count="$totalCount" :filters="$filters" :selects="$filterSelects" :action="route('admin.categories.index')" empty-title="Belum ada kategori" empty-description="Kategori barang akan tampil di sini setelah dibuat." />

        @if ($categories->isNotEmpty())
            <div class="mt-8 table-shell">
                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm">
                        <thead class="table-head">
                            <tr>
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3">Slug</th>
                                <th class="min-w-44 px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @foreach ($categories as $category)
                                <tr class="table-row">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-zinc-950">{{ $category->name }}</p>
                                        <p class="max-w-xl text-xs leading-5 text-zinc-500">{{ $category->description }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-zinc-600">{{ $category->slug }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="badge {{ $category->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-600' }}">
                                            {{ $category->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <x-button href="{{ route('admin.categories.edit', $category) }}" variant="outline">Edit</x-button>
                                            @if ($category->is_active)
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.categories.destroy', $category) }}"
                                                    data-confirm
                                                    data-confirm-title="Nonaktifkan kategori?"
                                                    data-confirm-message="Kategori ini akan disembunyikan dari pilihan kategori aktif."
                                                    data-confirm-button="Nonaktifkan"
                                                >
                                                    @csrf
                                                    @method('DELETE')
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
                    @foreach ($categories as $category)
                        <article class="space-y-3 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <h2 class="min-w-0 break-words font-semibold text-zinc-950">{{ $category->name }}</h2>
                                <span class="badge {{ $category->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-600' }}">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                            <p class="break-all text-sm text-zinc-500">Slug: {{ $category->slug }}</p>
                            <p class="break-words text-sm text-zinc-600">{{ $category->description }}</p>
                            <div class="flex flex-wrap gap-2">
                                <x-button :href="route('admin.categories.edit', $category)" variant="outline">Edit</x-button>
                                @if ($category->is_active)
                                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-confirm data-confirm-title="Nonaktifkan kategori?" data-confirm-message="Kategori ini akan disembunyikan dari pilihan kategori aktif." data-confirm-button="Nonaktifkan">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" variant="danger">Nonaktifkan</x-button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-6">{{ $categories->links() }}</div>
    </section>
@endsection
