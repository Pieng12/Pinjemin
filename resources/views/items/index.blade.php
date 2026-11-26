@extends('layouts.app')

@section('title', 'Jelajahi Barang - Pinjemin')
@section('nav_title', 'Menyewa Barang')
@section('meta_description', 'Jelajahi barang aktif yang dapat disewa di Pinjemin.')

@section('content')
    @php
        $periodKeys = ['start_date', 'end_date', 'quantity'];
        $activeFilters = collect($filters)->except(['sort', ...$periodKeys])->filter(fn ($value) => filled($value));
        $values = array_replace($filters, array_filter(session()->getOldInput(), 'is_scalar'));
        $filterCount = $activeFilters->except('q')->count() + ($period !== [] ? 1 : 0);
        $hasDetailedFilters = $errors->any() || $period !== [] || $activeFilters->except(['q', 'category'])->isNotEmpty();
        $filterLabels = ['q' => 'Pencarian', 'category' => 'Kategori', 'city' => 'Kota', 'condition' => 'Kondisi', 'min_price' => 'Harga minimum', 'max_price' => 'Harga maksimum'];
        $categoryFilters = collect($filters)->except('category')->all();
        $selectedCategory = $filters['category'] ?? '';
    @endphp
    <section class="page-shell catalog-page">
        <div data-reveal class="marketplace-reveal">
            <h1 class="text-2xl font-semibold tracking-tight text-brand-800 sm:text-3xl">Temukan barang untuk kebutuhan Anda</h1>
            <p class="mt-1.5 text-sm leading-6 text-zinc-600">Cari berdasarkan kategori, lokasi, harga, atau ketersediaan pada periode sewa Anda.</p>
        </div>

        <form method="GET" action="{{ route('browse') }}" class="mt-5" data-catalog-search>
            <div class="rounded-lg border border-brand-100 bg-linear-to-br from-brand-50 to-white p-3 sm:p-4">
                <div class="catalog-search">
                    <div class="catalog-search-row">
                        <div class="relative min-w-0">
                            <label for="q" class="sr-only">Cari barang</label>
                            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" aria-hidden="true"></i>
                            <input id="q" type="search" name="q" value="{{ $values['q'] ?? '' }}" maxlength="255" placeholder="Kamera, tenda, proyektor..." class="input pl-10">
                        </div>
                        <x-button type="submit" class="min-h-11">Cari</x-button>
                    </div>
                    <details class="catalog-filter-details" @if ($hasDetailedFilters) open @endif>
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
                                            <option value="{{ $category->slug }}" @selected(($values['category'] ?? '') === $category->slug)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="city" class="field-label">Kota</label>
                                    <select id="city" name="city" class="input">
                                        <option value="">Semua kota</option>
                                        @foreach ($cities as $city)
                                            <option value="{{ $city }}" @selected(($values['city'] ?? '') === $city)>{{ $city }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="condition" class="field-label">Kondisi</label>
                                    <select id="condition" name="condition" class="input">
                                        <option value="">Semua kondisi</option>
                                        @foreach ($conditions as $value => $label)
                                            <option value="{{ $value }}" @selected(($values['condition'] ?? '') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="min_price" class="field-label">Harga minimum (Rp)</label>
                                    <input id="min_price" name="min_price" type="number" min="0" step="any" inputmode="decimal" value="{{ $values['min_price'] ?? '' }}" class="input" placeholder="0" @error('min_price') aria-invalid="true" aria-describedby="catalog-min-price-error" @enderror>
                                    @error('min_price')<p id="catalog-min-price-error" class="field-error">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="max_price" class="field-label">Harga maksimum (Rp)</label>
                                    <input id="max_price" name="max_price" type="number" min="0" step="any" inputmode="decimal" value="{{ $values['max_price'] ?? '' }}" class="input" placeholder="Tanpa batas" @error('max_price') aria-invalid="true" aria-describedby="catalog-max-price-error" @enderror>
                                    @error('max_price')<p id="catalog-max-price-error" class="field-error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <fieldset class="mt-4 min-w-0">
                                <legend class="font-semibold text-zinc-900">Ketersediaan pada periode sewa</legend>
                                <p id="catalog-period-help" class="mt-1 text-sm text-zinc-600">Isi kedua tanggal untuk mencari stok selama seluruh periode (WIB). Kosongkan jumlah untuk mencari satu unit.</p>
                                <div class="mt-2 grid gap-4 sm:grid-cols-3">
                                    @foreach(['start_date' => 'Tanggal mulai', 'end_date' => 'Tanggal pengembalian', 'quantity' => 'Jumlah unit'] as $name => $label)
                                        <div class="min-w-0">
                                            <label for="catalog-{{ $name }}" class="field-label">{{ $label }}</label>
                                            <input id="catalog-{{ $name }}" name="{{ $name }}" value="{{ $values[$name] ?? '' }}" class="input min-w-0"
                                                @if($name === 'quantity') type="number" min="1" step="1" inputmode="numeric" placeholder="1" @else type="date" min="{{ now(\App\Models\Booking::RentalTimezone)->toDateString() }}" @endif
                                                aria-describedby="catalog-period-help @error($name) catalog-{{ $name }}-error @enderror" @error($name) aria-invalid="true" @enderror>
                                            @error($name)<p id="catalog-{{ $name }}-error" class="field-error">{{ $message }}</p>@enderror
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>
                            <div class="mt-5 flex flex-wrap justify-end gap-3 border-t border-zinc-100 pt-4">
                                <x-button :href="route('browse')" variant="outline">Reset</x-button>
                                <x-button type="submit">Terapkan</x-button>
                            </div>
                        </div>
                    </details>
                </div>

                @if($quickCategories->isNotEmpty())
                    <nav class="mt-3 flex max-w-full gap-2 overflow-x-auto px-0.5 py-1" aria-label="Kategori barang">
                        @foreach(collect(['' => 'Semua'])->merge($quickCategories->pluck('name', 'slug')) as $slug => $label)
                            @php
                                $categoryActive = $slug === $selectedCategory;
                            @endphp
                            <a href="{{ route('browse', $slug === '' ? $categoryFilters : $categoryFilters + ['category' => $slug]) }}"
                                class="inline-flex min-h-11 shrink-0 items-center whitespace-nowrap rounded-lg border px-3.5 text-sm font-semibold {{ $categoryActive ? 'border-brand-700 bg-brand-700 text-white' : 'border-brand-100 bg-white text-brand-700 hover:border-brand-300 hover:bg-brand-50' }}"
                                @if($categoryActive) aria-current="true" @endif>{{ $label }}</a>
                        @endforeach
                    </nav>
                @endif

                @if ($activeFilters->isNotEmpty() || $period !== [])
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
                        @if($period !== [])
                            <a href="{{ route('browse', collect($filters)->except($periodKeys)->all()) }}" class="catalog-filter-chip" aria-label="Hapus filter periode dan jumlah unit">
                                {{ \Illuminate\Support\Carbon::parse($period['start_date'])->locale('id')->translatedFormat('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($period['end_date'])->locale('id')->translatedFormat('d M Y') }} · {{ $period['quantity'] }} unit
                                <i data-lucide="x" class="size-3.5 shrink-0" aria-hidden="true"></i>
                            </a>
                        @endif
                    </div>
                @endif
            </div>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-2">
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

        <div class="mt-3.5">
            @if ($items->count())
                <div class="grid grid-cols-1 gap-3 min-[360px]:grid-cols-2 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($items as $item)
                    <x-item-card :item="$item" :period="$period" data-reveal class="marketplace-reveal" style="--reveal-delay: {{ min($loop->index * 40, 200) }}ms" />
                    @endforeach
                </div>
                <div class="mt-8">{{ $items->links() }}</div>
            @elseif($catalogHasItems)
                <x-empty-state :show-mark="false" title="Barang tidak ditemukan." description="Sesuaikan kata pencarian atau filter yang digunakan." action-label="Reset Filter" :action-url="route('browse')" />
            @else
                <x-empty-state :show-mark="false" title="Belum ada barang yang tersedia." description="Barang yang diterbitkan oleh pemilik akan tampil di katalog." action-label="Sewakan Barang" :action-url="route('rent-out')" />
            @endif
        </div>
    </section>
@endsection
