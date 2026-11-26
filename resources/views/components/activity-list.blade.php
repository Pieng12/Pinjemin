@props(['activity', 'title' => 'Tindakan yang diperlukan', 'compact' => false])
<section {{ $attributes->class(['card card-pad mt-6' => $compact, 'card card-pad mt-7' => ! $compact]) }} aria-label="{{ $title }}" data-activity-list>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h2 class="text-lg font-semibold">{{ $title }}</h2>@if (! $compact || $activity['bookingCount'] > 0)<p class="mt-1 text-sm text-zinc-600">{{ $activity['bookingCount'] }} pemesanan membutuhkan tindakan Anda.@if($activity['identityCount']) {{ $activity['identityCount'] }} pengajuan identitas menunggu pemeriksaan.@endif</p>@endif</div>
        @if (! $compact)
            <span class="badge bg-brand-50 text-brand-700">{{ count($activity['tasks']) }} ditampilkan</span>
        @elseif ($activity['bookingCount'] > count($activity['tasks']))
            <p class="text-xs text-zinc-600">{{ count($activity['tasks']) }} dari {{ $activity['bookingCount'] }} ditampilkan</p>
        @endif
    </div>
    <div @class(['mt-3 divide-y divide-zinc-200' => $compact, 'mt-5 grid gap-3' => ! $compact])>
        @forelse($activity['tasks'] as $task)
            <a href="{{ $task['href'] }}" @class(['flex min-w-0 flex-col justify-between gap-2 transition-colors hover:bg-brand-50/40 sm:flex-row sm:items-center', 'px-2 py-3' => $compact, 'rounded-md border border-zinc-200 p-4 hover:border-brand-300' => ! $compact])>
                <div class="min-w-0"><h3 class="font-semibold text-zinc-950">{{ $task['title'] }}</h3><p class="mt-1 break-words text-sm text-zinc-600">{{ $task['context'] }}</p>@if($task['deadline'])<p class="mt-1 text-xs font-medium text-amber-800">Batas {{ $task['deadline']->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB</p>@endif</div>
                <span class="inline-flex min-h-9 shrink-0 items-center gap-2 text-sm font-semibold text-brand-700">{{ $task['label'] }} <i data-lucide="arrow-right" class="size-4" aria-hidden="true"></i></span>
            </a>
        @empty
            <p @class(['text-sm leading-6 text-zinc-600', 'rounded-md bg-zinc-50 p-4' => ! $compact])>Tidak ada tindakan yang perlu diselesaikan saat ini.</p>
        @endforelse
    </div>
</section>
