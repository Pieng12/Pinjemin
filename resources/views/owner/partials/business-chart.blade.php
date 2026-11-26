@php
    $isCurrency = $metric === 'transferredCents';
    $peak = max(array_column($report['periods'], $metric));
    $interval = (int) $report['periods'][0]['from']->diffInDays($report['periods'][0]['through']) + 1;
@endphp

<article class="card card-pad min-w-0" aria-labelledby="{{ $id }}">
    <h3 id="{{ $id }}" class="text-lg font-semibold text-zinc-950">{{ $title }}</h3>
    <p class="mt-1 text-sm leading-6 text-zinc-600">{{ $description }}</p>

    @if ($peak > 0)
        <div class="mt-5 flex flex-wrap items-center justify-between gap-x-2 gap-y-1 text-xs text-zinc-600">
            <span>{{ $interval === 1 ? 'Per hari' : 'Per '.$interval.' hari' }}</span>
            <span>Nilai tertinggi <strong class="text-zinc-950 tabular-nums">@if ($isCurrency)<x-price :amount="$peak / 100" suffix="" />@else{{ number_format($peak, 0, ',', '.') }}@endif</strong></span>
        </div>
        <div class="relative mt-2 min-w-0 border-b border-zinc-200 pb-5" aria-hidden="true">
            <div class="pointer-events-none absolute inset-x-0 top-0 bottom-5 flex flex-col justify-between" aria-hidden="true">
                <span class="border-t border-dashed border-zinc-200"></span>
                <span class="border-t border-dashed border-zinc-200"></span>
                <span class="border-t border-zinc-200"></span>
            </div>
            <div class="relative grid h-32 gap-1 px-1 sm:h-40 sm:gap-3" style="grid-template-columns: repeat({{ count($report['periods']) }}, minmax(0, 1fr))">
                @foreach ($report['periods'] as $period)
                    @php($value = $period[$metric])
                    <div class="relative flex h-full items-end justify-center">
                        <div @class(['w-full max-w-12 rounded-t-md', 'bg-brand-700' => $isCurrency, 'bg-brand-600' => ! $isCurrency])
                            style="height: {{ $value / $peak * 100 }}%{{ $value > 0 ? '; min-height: 2px' : '' }}"
                            title="{{ $period['from']->locale('id')->translatedFormat('j M') }}{{ $period['from']->ne($period['through']) ? ' – '.$period['through']->locale('id')->translatedFormat('j M') : '' }} · {{ $isCurrency ? 'Rp '.number_format($value / 100, $value % 100 === 0 ? 0 : 2, ',', '.') : $value.' pengajuan' }}"></div>
                        <span class="absolute top-full pt-1 text-[11px] text-zinc-600 tabular-nums">{{ $period['from']->format('j/n') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <details class="group mt-2">
            <summary class="flex min-h-11 cursor-pointer items-center justify-between gap-2 text-sm font-semibold text-brand-800">
                Lihat data per interval
                <i data-lucide="chevron-down" class="size-4 group-open:rotate-180 motion-safe:transition-transform" aria-hidden="true"></i>
            </summary>
            <table class="w-full text-sm">
                <caption class="sr-only">{{ $title }} per interval</caption>
                <thead><tr class="border-b border-zinc-200 text-zinc-600"><th scope="col" class="py-2 text-left font-medium">Tanggal</th><th scope="col" class="py-2 text-right font-medium">{{ $isCurrency ? 'Dana ditransfer' : 'Pengajuan' }}</th></tr></thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($report['periods'] as $period)
                        <tr>
                            <th scope="row" class="py-2 pe-2 text-left font-medium text-zinc-700">
                                {{ $period['from']->locale('id')->translatedFormat('j M') }}
                                @if ($period['from']->ne($period['through'])) – {{ $period['through']->locale('id')->translatedFormat('j M') }} @endif
                            </th>
                            <td class="py-2 text-right text-zinc-950 tabular-nums">
                                @if ($isCurrency) <x-price :amount="$period[$metric] / 100" suffix="" />
                                @else {{ number_format($period[$metric], 0, ',', '.') }} @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @else
        <p class="mt-5 rounded-md bg-zinc-50 p-4 text-sm text-zinc-600">{{ $emptyText }}</p>
    @endif

    @if ($isCurrency)
        <div class="mt-3 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 border-t border-zinc-100 pt-3 text-sm">
            <p class="text-zinc-600">Komisi pada transfer <x-price :amount="$report['commissionCents'] / 100" suffix="" class="font-semibold text-zinc-950" /></p>
            <a href="{{ route('owner.payouts') }}" class="inline-flex min-h-11 items-center font-semibold text-brand-800 hover:underline">Lihat Pencairan</a>
        </div>
    @endif
</article>
