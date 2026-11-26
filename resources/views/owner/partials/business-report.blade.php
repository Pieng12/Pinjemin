<section id="kinerja-usaha" aria-labelledby="business-report-title" class="mt-9 scroll-mt-24 border-t border-brand-100 pt-7">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 id="business-report-title" class="text-xl font-semibold text-zinc-950 sm:text-2xl">Kinerja Penyewaan</h2>
            <p class="mt-1 text-sm text-zinc-600">
                {{ $report['from']->locale('id')->translatedFormat('j M Y') }} – {{ $report['through']->locale('id')->translatedFormat('j M Y') }} WIB
            </p>
        </div>
        <nav aria-label="Periode laporan" class="flex flex-wrap gap-1 rounded-lg border border-zinc-200 bg-white p-1">
            @foreach ([7, 30, 90] as $days)
                <a href="{{ route('owner.dashboard', ['period' => $days]) }}#kinerja-usaha"
                    @if ($report['days'] === $days) aria-current="true" @endif
                    @class(['inline-flex min-h-11 items-center rounded-md px-3 text-sm font-semibold', 'bg-brand-700 text-white' => $report['days'] === $days, 'text-zinc-700 hover:bg-brand-50 hover:text-brand-800' => $report['days'] !== $days])>
                    {{ $days }} hari
                </a>
            @endforeach
        </nav>
    </header>

    <dl class="mt-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div class="rounded-lg border border-brand-100 bg-brand-50 p-3 sm:p-4">
            <dt class="text-sm font-semibold text-brand-800">Dana ditransfer</dt>
            <dd class="mt-2">
                <x-price :amount="$report['transferredCents'] / 100" suffix="" class="text-xl font-semibold wrap-anywhere text-brand-800 tabular-nums sm:text-2xl" />
                <span class="mt-1 block text-xs text-zinc-600">Setelah komisi Pinjemin</span>
            </dd>
        </div>
        @foreach ([
            ['Pemesanan masuk', 'orders', 'Semua pengajuan dalam periode'],
            ['Penyewa unik', 'renters', 'Setiap akun dihitung satu kali'],
            ['Pemesanan selesai', 'completed', 'Dari pengajuan dalam periode'],
        ] as [$label, $key, $description])
            <div class="rounded-lg border border-zinc-200 bg-white p-3 sm:p-4">
                <dt class="text-sm font-semibold text-zinc-700">{{ $label }}</dt>
                <dd class="mt-2">
                    <span class="text-xl font-semibold text-zinc-950 tabular-nums sm:text-2xl">{{ number_format($report[$key], 0, ',', '.') }}</span>
                    <span class="mt-1 block text-xs text-zinc-600">{{ $description }}</span>
                </dd>
            </div>
        @endforeach
    </dl>

    <div class="mt-4 grid min-w-0 gap-4 xl:grid-cols-2">
        @include('owner.partials.business-chart', [
            'id' => 'owner-transfer-chart',
            'title' => 'Tren Pencairan',
            'description' => 'Dana yang ditransfer melalui Pinjemin, dikelompokkan menurut tanggal transfer.',
            'metric' => 'transferredCents',
            'emptyText' => 'Belum ada transfer yang tercatat pada periode ini.',
        ])
        @include('owner.partials.business-chart', [
            'id' => 'owner-order-chart',
            'title' => 'Tren Pemesanan',
            'description' => 'Jumlah pengajuan berdasarkan tanggal pemesanan.',
            'metric' => 'orders',
            'emptyText' => 'Belum ada pengajuan pada periode ini.',
        ])
    </div>

    <div class="mt-4 grid min-w-0 items-start gap-4 xl:grid-cols-2">
        <article class="card card-pad min-w-0" aria-labelledby="owner-popular-items">
            <h3 id="owner-popular-items" class="text-lg font-semibold text-zinc-950">Barang Paling Sering Dipesan</h3>
            <p class="mt-1 text-sm text-zinc-600">Hingga lima barang berdasarkan jumlah pengajuan.</p>
            @if ($report['popularItems'] !== [])
                @php($mostOrders = $report['popularItems'][0]['orders'])
                <ol class="mt-5 space-y-4">
                    @foreach ($report['popularItems'] as $item)
                        <li class="min-w-0">
                            <div class="flex items-start justify-between gap-3 text-sm">
                                <a href="{{ route('my-items.show', ['item' => $item['slug']]) }}" class="min-w-0 font-semibold wrap-anywhere text-brand-800 hover:underline">{{ $item['name'] }}</a>
                                <span class="shrink-0 text-right tabular-nums"><strong>{{ number_format($item['orders'], 0, ',', '.') }}</strong> pengajuan</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-brand-50" aria-hidden="true">
                                <div class="h-full rounded-full bg-brand-600" style="width: {{ $item['orders'] / $mostOrders * 100 }}%"></div>
                            </div>
                            <p class="mt-1 text-xs text-zinc-600">{{ number_format($item['completed'], 0, ',', '.') }} selesai</p>
                        </li>
                    @endforeach
                </ol>
            @else
                <p class="mt-5 rounded-md bg-zinc-50 p-4 text-sm text-zinc-600">Peringkat akan muncul setelah ada pengajuan pada periode ini.</p>
            @endif
        </article>

        <article class="card card-pad min-w-0" aria-labelledby="owner-order-statuses">
            <h3 id="owner-order-statuses" class="text-lg font-semibold text-zinc-950">Status Pemesanan</h3>
            <p class="mt-1 text-sm text-zinc-600">Status terkini dari pengajuan pada periode pilihan.</p>
            @if ($report['orders'] > 0)
                <ul class="mt-5 space-y-3.5">
                    @foreach ([
                        ['pending', 'Menunggu persetujuan', 'bg-amber-500'],
                        ['in_progress', 'Konfirmasi dan pembayaran', 'bg-brand-300'],
                        ['approved', 'Disetujui', 'bg-brand-600'],
                        ['completed', 'Selesai', 'bg-brand-800'],
                        ['rejected', 'Ditolak', 'bg-zinc-400'],
                        ['cancelled', 'Dibatalkan', 'bg-zinc-400'],
                        ['expired', 'Kedaluwarsa', 'bg-zinc-400'],
                    ] as [$key, $label, $color])
                        @php($count = $report['statuses'][$key])
                        <li>
                            <div class="flex justify-between gap-3 text-sm"><span class="text-zinc-700">{{ $label }}</span><strong class="tabular-nums">{{ number_format($count, 0, ',', '.') }}</strong></div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-zinc-100" aria-hidden="true">
                                <div class="h-full rounded-full {{ $color }}" style="width: {{ $count / $report['orders'] * 100 }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-5 rounded-md bg-zinc-50 p-4 text-sm text-zinc-600">Belum ada status yang dapat ditampilkan.</p>
            @endif
        </article>
    </div>
</section>
