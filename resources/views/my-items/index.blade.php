@extends('layouts.owner')

@section('title', 'Barang Saya - Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="['Dashboard Pemilik' => route('owner.dashboard'), 'Barang Saya' => null]" />
        <div class="page-header reveal mt-6">
            <div>
                <h1 class="text-3xl font-semibold text-zinc-950">Barang Saya</h1>
                <p class="mt-2 text-zinc-600">Kelola barang yang kamu sewakan.</p>
            </div>
            <x-button :href="route('my-items.create')"><i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i> Tambah Barang</x-button>
        </div>

        <div class="mt-8">
            @if ($items->count())
                <div class="grid gap-4">
                    @foreach ($items as $item)
                        <article class="owner-inventory-card marketplace-reveal" data-reveal style="--reveal-delay: {{ min($loop->index * 40, 200) }}ms">
                            <a href="{{ route('my-items.show', $item) }}" class="block min-w-0 self-start" aria-label="Lihat {{ $item->name }}"><x-item-image :item="$item" class="aspect-[4/3]" /></a>
                            <div class="min-w-0">
                                <x-status-badge :item="$item" />
                                <h2 class="mt-2 break-words text-lg font-semibold text-zinc-950"><a href="{{ route('my-items.show', $item) }}" class="hover:text-blue-700">{{ $item->name }}</a></h2>
                                <p class="mt-1 text-sm text-zinc-500">{{ $item->category->name }} / {{ $item->quantity }} unit / Dibuat {{ $item->created_at->format('d M Y') }}</p>
                                <x-price :amount="$item->daily_price" class="mt-3 block font-semibold text-blue-700" />
                            </div>
                            <div class="owner-inventory-actions">
                                <x-button :href="route('my-items.show', $item)" variant="outline" class="min-h-10">Lihat</x-button>
                                <x-button :href="route('my-items.edit', $item)" variant="outline" class="min-h-10">Edit</x-button>
                                <details class="w-full rounded-md border border-zinc-200 p-3 text-sm"><summary class="cursor-pointer font-semibold">Persyaratan Foto &amp; Identitas</summary><form method="POST" action="{{ route('my-items.requirements', $item) }}" class="mt-3 grid gap-3">@csrf @method('PATCH')<input type="hidden" name="requires_condition_photos" value="0"><label class="flex gap-2"><input type="checkbox" name="requires_condition_photos" value="1" @checked($item->requires_condition_photos)> Wajib foto kondisi dua pihak</label><input type="hidden" name="requires_identity_verification" value="0"><label class="flex gap-2"><input type="checkbox" name="requires_identity_verification" value="1" @checked($item->requires_identity_verification)> Wajib verifikasi identitas penyewa</label><p class="text-xs text-zinc-500">Hanya mengubah booking berikutnya.</p><x-button type="submit" variant="outline">Simpan Persyaratan</x-button></form></details>
                                <form method="POST" action="{{ route('my-items.toggle-status', $item) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-outline min-h-10">{{ $item->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                                <form method="POST" action="{{ route('my-items.destroy', $item) }}" data-confirm data-confirm-title="Hapus barang?" data-confirm-description="Barang akan dihapus permanen jika belum mempunyai riwayat booking.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger min-h-10">Hapus</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="mt-6">{{ $items->links() }}</div>
            @else
                <x-empty-state :show-mark="false" title="Belum ada barang yang kamu sewakan." description="Sewakan barang yang jarang digunakan dan mulai dapatkan manfaat darinya." action-label="Tambah Barang" :action-url="route('my-items.create')" />
            @endif
        </div>
    </section>
@endsection
