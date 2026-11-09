@props(['action'])
<section class="mt-6 rounded-lg border border-brand-100 bg-brand-50 p-5 sm:p-6" aria-labelledby="next-action-title" data-next-action>
    <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Langkah selanjutnya</p>
    <div class="mt-2 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div class="min-w-0"><h2 id="next-action-title" class="text-lg font-semibold text-brand-800">{{ $action['title'] }}</h2><p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-700">{{ $action['description'] }}</p>@if($action['deadline'])<p class="mt-2 text-sm font-semibold text-amber-900">Batas {{ $action['deadline']->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB</p>@endif</div>
        @if($action['href'])<x-button :href="$action['href']" class="shrink-0 self-start" data-action-link>{{ $action['label'] }}</x-button>@endif
    </div>
</section>
