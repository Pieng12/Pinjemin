@props(['activity', 'title' => 'Perlu perhatianmu'])
<section class="card card-pad mt-7" aria-label="{{ $title }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h2 class="text-lg font-semibold">{{ $title }}</h2><p class="mt-1 text-sm text-zinc-600">{{ $activity['bookingCount'] }} booking membutuhkan tindakanmu.@if($activity['identityCount']) {{ $activity['identityCount'] }} pengajuan identitas menunggu pemeriksaan.@endif</p></div>
        <span class="badge bg-brand-50 text-brand-700">{{ count($activity['tasks']) }} ditampilkan</span>
    </div>
    <div class="mt-5 grid gap-3">
        @forelse($activity['tasks'] as $task)
            <a href="{{ $task['href'] }}" class="flex min-w-0 flex-col justify-between gap-3 rounded-md border border-zinc-200 p-4 transition-colors hover:border-brand-300 hover:bg-brand-50/40 sm:flex-row sm:items-center">
                <div class="min-w-0"><h3 class="font-semibold text-brand-700">{{ $task['title'] }}</h3><p class="mt-1 break-words text-sm text-zinc-600">{{ $task['context'] }}</p>@if($task['deadline'])<p class="mt-1 text-xs font-medium text-amber-800">Batas {{ $task['deadline']->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB</p>@endif</div>
                <span class="inline-flex min-h-11 shrink-0 items-center gap-2 text-sm font-semibold">{{ $task['label'] }} <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i></span>
            </a>
        @empty
            <p class="rounded-md bg-zinc-50 p-4 text-sm leading-6 text-zinc-600">Tidak ada tindakan yang menunggumu saat ini. Riwayat dan daftar lengkap tetap tersedia dari navigasi.</p>
        @endforelse
    </div>
</section>
