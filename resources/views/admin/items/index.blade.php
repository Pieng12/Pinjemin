@extends('layouts.admin')

@section('title', 'Barang - Admin Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="[
            'Admin' => route('admin.dashboard'),
            'Barang' => null,
        ]" />

        <div class="page-header mt-6">
            <div>
                <p class="eyebrow">Moderasi katalog</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-zinc-950">Barang</h1>
                <p class="mt-2 text-zinc-600">Daftar barang dari seluruh pengguna Pinjemin.</p>
            </div>
        </div>

        <div class="mt-8 table-shell">
            <div class="overflow-x-auto">
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
                        @forelse ($items as $item)
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
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10">
                                    <x-empty-state title="Belum ada barang" description="Barang dari pengguna akan tampil di sini." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $items->links() }}</div>
    </section>
@endsection
