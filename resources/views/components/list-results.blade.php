@props(['results', 'totalCount', 'filters' => [], 'selects' => [], 'action', 'emptyTitle', 'emptyDescription', 'emptyActionLabel' => null, 'emptyActionUrl' => null])

<div {{ $attributes->merge(['class' => 'mt-4 space-y-3']) }}>
    <p class="text-sm text-zinc-600" role="status">
        Menampilkan {{ $results->firstItem() ?? 0 }}–{{ $results->lastItem() ?? 0 }} dari {{ $results->total() }} hasil ({{ $totalCount }} total).
    </p>
    @if (collect($filters)->contains(fn ($value) => filled($value)))
        <nav class="flex flex-wrap gap-2" aria-label="Filter aktif">
            @foreach ($filters as $name => $value)
                @if (filled($value))
                    @php
                        $label = $name === 'q' ? 'Pencarian' : ($selects[$name]['label'] ?? $name);
                        $displayValue = $selects[$name]['options'][$value] ?? $value;
                    @endphp
                    <a href="{{ request()->fullUrlWithoutQuery([$name, 'page']) }}" class="inline-flex min-h-10 max-w-full items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800 hover:bg-blue-100 focus-visible:outline-2 focus-visible:outline-blue-600" aria-label="Hapus filter {{ $label }}: {{ $displayValue }}">
                        <span class="break-words">{{ $label }}: {{ $displayValue }}</span><span aria-hidden="true">×</span>
                    </a>
                @endif
            @endforeach
        </nav>
    @endif
    @if ($results->isEmpty())
        @if ($totalCount === 0)
            <x-empty-state :title="$emptyTitle" :description="$emptyDescription" :action-label="$emptyActionLabel" :action-url="$emptyActionUrl" />
        @elseif ($results->total() === 0)
            <x-empty-state title="Tidak ada hasil yang cocok" description="Gunakan kata pencarian lain atau sesuaikan filter untuk melihat hasil." action-label="Reset filter" :action-url="$action" />
        @else
            <x-empty-state title="Halaman ini tidak berisi hasil" description="Kembali ke halaman pertama untuk melihat hasil pencarian." action-label="Halaman pertama" :action-url="request()->fullUrlWithoutQuery('page')" />
        @endif
    @endif
</div>
