@extends('layouts.app')

@section('title', 'Jelajahi Barang - Pinjemin')
@section('nav_title', 'Menyewa Barang')
@section('meta_description', 'Jelajahi barang aktif yang bisa disewa di Pinjemin.')

@section('content')
    @php
        $activeFilters = collect($filters)->filter(fn ($value, $key) => filled($value) && $key !== 'sort');
        $filterCount = $activeFilters->except('q')->count();
        $filterLabels = ['q' => 'Pencarian', 'category' => 'Kategori', 'city' => 'Kota', 'condition' => 'Kondisi', 'min_price' => 'Harga minimum', 'max_price' => 'Harga maksimum'];
    @endphp
    <section class="page-shell catalog-page">
        <div data-reveal class="marketplace-reveal">
            <p class="eyebrow">Marketplace</p>
            <h1 class="mt-2 text-2xl font-semibold leading-tight text-zinc-950 sm:text-3xl">Temukan barang untuk rencanamu</h1>
            <p class="mt-3 text-sm leading-6 text-zinc-600">Pilih barang, lokasi, dan harga sewa yang sesuai kebutuhanmu.</p>
        </div>

        <form method="GET" action="{{ route('browse') }}" class="catalog-search mt-7" data-catalog-search>
            <div class="catalog-search-row">
                <div class="relative min-w-0">
                    <label for="q" class="sr-only">Cari barang</label>
                    <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" aria-hidden="true"></i>
                    <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Kamera, tenda, proyektor..." class="input pl-10">
                </div>
                <x-button type="submit" class="min-h-11">Cari</x-button>
            </div>
            <details class="catalog-filter-details" @if ($filterCount) open @endif>
                <summary class="catalog-filter-toggle">
                    <i data-lucide="sliders-horizontal" class="h-4 w-4" aria-hidden="true"></i> Filter
                    @if ($filterCount)<span class="catalog-filter-count">{{ $filterCount }}</span>@endif
                    <i data-lucide="chevron-down" class="h-4 w-4 catalog-filter-chevron" aria-hidden="true"></i>
                </summary>
                <div class="catalog-filter-panel">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                        <div>
                            <label for="category" class="field-label">Kategori</label>
                            <select id="category" name="category" class="input">
                                <option value="">Semua kategori</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="city" class="field-label">Kota</label>
                            <select id="city" name="city" class="input">
                                <option value="">Semua kota</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city }}" @selected(($filters['city'] ?? '') === $city)>{{ $city }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="condition" class="field-label">Kondisi</label>
                            <select id="condition" name="condition" class="input">
                                <option value="">Semua kondisi</option>
                                @foreach ($conditions as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['condition'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="min_price" class="field-label">Harga minimum (Rp)</label>
                            <input id="min_price" name="min_price" type="number" min="0" step="any" inputmode="decimal" value="{{ $filters['min_price'] ?? '' }}" class="input" placeholder="0">
                        </div>
                        <div>
                            <label for="max_price" class="field-label">Harga maksimum (Rp)</label>
                            <input id="max_price" name="max_price" type="number" min="0" step="any" inputmode="decimal" value="{{ $filters['max_price'] ?? '' }}" class="input" placeholder="Tanpa batas">
                        </div>
                    </div>
                    <div class="mt-5 flex flex-wrap justify-end gap-3 border-t border-zinc-100 pt-4">
                        <x-button :href="route('browse')" variant="outline">Reset</x-button>
                        <x-button type="submit">Terapkan</x-button>
                    </div>
                </div>
            </details>

            @if ($activeFilters->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2" aria-label="Filter aktif">
                    @foreach ($activeFilters as $key => $value)
                        @php
                            $displayValue = match ($key) {
                                'category' => $categories->firstWhere('slug', $value)?->name ?? $value,
                                'condition' => $conditions[$value] ?? $value,
                                default => $value,
                            };
                        @endphp
                        <a href="{{ route('browse', collect($filters)->except($key)->all()) }}" class="catalog-filter-chip" aria-label="Hapus filter {{ $filterLabels[$key] }}">
                            <span class="min-w-0 break-words">{{ $filterLabels[$key] }}:
                                @if (in_array($key, ['min_price', 'max_price'], true))
                                    <x-price :amount="$value" suffix="" />
                                @else
                                    {{ $displayValue }}
                                @endif
                            </span>
                            <i data-lucide="x" class="h-3.5 w-3.5 shrink-0" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="mt-7 flex flex-col gap-3 border-t border-zinc-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-zinc-600">Menampilkan <span class="font-semibold text-zinc-900">{{ $items->firstItem() ?? 0 }}-{{ $items->lastItem() ?? 0 }}</span> dari {{ $items->total() }} barang</p>
                <div class="flex items-center gap-2">
                    <label for="sort" class="text-sm text-zinc-500">Urutkan</label>
                    <select id="sort" name="sort" class="input mt-0 w-auto min-w-0 flex-1 sm:flex-none" data-catalog-sort>
                        <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Terbaru</option>
                        <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Harga termurah</option>
                        <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Harga termahal</option>
                    </select>
                    <button type="submit" class="btn btn-outline min-h-11 px-3" aria-label="Terapkan urutan" title="Terapkan urutan"><i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i><span class="sr-only">Terapkan urutan</span></button>
                </div>
            </div>
        </form>

        <div class="mt-5">
            @if ($items->count())
                <div class="grid grid-cols-1 gap-3 min-[360px]:grid-cols-2 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($items as $item)
                        <x-item-card :item="$item" data-reveal class="marketplace-reveal" style="--reveal-delay: {{ min($loop->index * 40, 200) }}ms" />
                    @endforeach
                </div>
                <div class="mt-8">{{ $items->links() }}</div>
            @else
                <x-empty-state :show-mark="false" title="Barang tidak ditemukan." description="Coba ubah kata pencarian atau filter yang digunakan." action-label="Reset Filter" :action-url="route('browse')" />
            @endif
        </div>
    </section>
@endsection
