@extends('layouts.admin')

@section('title', 'Kategori - Admin Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="[
            'Admin' => route('admin.dashboard'),
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

        <div class="mt-8 table-shell">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="table-head">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($categories as $category)
                            <tr class="table-row">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-zinc-950">{{ $category->name }}</p>
                                    <p class="max-w-xl text-xs leading-5 text-zinc-500">{{ $category->description }}</p>
                                </td>
                                <td class="px-4 py-3 text-zinc-600">{{ $category->slug }}</td>
                                <td class="px-4 py-3">
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
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10">
                                    <x-empty-state title="Belum ada kategori" description="Kategori barang akan tampil di sini setelah dibuat." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $categories->links() }}</div>
    </section>
@endsection
