@props(['action', 'filters' => [], 'searchLabel' => 'Cari', 'placeholder' => '', 'selects' => [], 'compact' => false])

<form method="GET" action="{{ $action }}" {{ $attributes->merge(['class' => 'soft-card mt-6 flex flex-col gap-4 p-4 sm:flex-row sm:flex-wrap sm:items-end']) }} role="search" aria-label="Pencarian dan filter daftar">
    <div class="min-w-0 flex-1 sm:min-w-56">
        <label for="list-q" class="text-sm font-semibold text-zinc-700">{{ $searchLabel }}</label>
        <input id="list-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ $placeholder }}" class="input mt-1 w-full">
    </div>
    @foreach ($selects as $name => $select)
        <div class="min-w-0 sm:w-48">
            <label for="list-{{ $name }}" class="text-sm font-semibold text-zinc-700">{{ $select['label'] }}</label>
            <select id="list-{{ $name }}" name="{{ $name }}" class="input mt-1 w-full">
                <option value="">{{ $select['all'] }}</option>
                @foreach ($select['options'] as $value => $label)
                    <option value="{{ $value }}" @selected((string) ($filters[$name] ?? '') === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endforeach
    <div class="flex flex-wrap gap-2">
        <x-button type="submit">Terapkan</x-button>
        @if (! $compact || collect($filters)->contains(fn ($value) => filled($value)))
            <x-button :href="$action" variant="outline">Reset</x-button>
        @endif
    </div>
</form>
