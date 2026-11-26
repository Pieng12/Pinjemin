@extends('layouts.app')

@section('transparent_nav', 'true')
@section('title', 'Pinjemin - Layanan Penyewaan Barang')
@section('meta_description', 'Pinjemin mempertemukan orang yang membutuhkan barang untuk sementara dengan pemilik barang yang ingin membuat barangnya kembali bermanfaat.')

@section('content')
    <section id="beranda" class="home-hero relative isolate overflow-hidden" data-home-collage>
        <div class="home-collage-stage">
            @foreach ([
                ['camera', 'hero-camera.jpg', 'Kamera DSLR', '-5deg', '0.2', '0ms', '7s'],
                ['lens', 'lens.jpg', 'Lensa kamera', '4deg', '-0.16', '100ms', '8s'],
                ['tent', 'tent.jpg', 'Tenda untuk camping', '-3deg', '0.28', '180ms', '9s'],
                ['audio', 'audio.jpg', 'Headphone untuk kebutuhan audio', '5deg', '-0.24', '80ms', '8.5s'],
                ['projector', 'projector.jpg', 'Proyektor LCD', '-4deg', '0.18', '220ms', '7.5s'],
                ['tools', 'tools.jpg', 'Bor untuk kebutuhan perkakas', '3deg', '-0.3', '260ms', '9.5s'],
            ] as [$name, $photo, $alt, $rotation, $speed, $delay, $duration])
                <div class="home-photo home-photo-{{ $name }}" data-collage-photo data-speed="{{ $speed }}" style="--photo-rotation: {{ $rotation }}; --photo-delay: {{ $delay }}; --float-duration: {{ $duration }}">
                    <div class="home-photo-parallax"><div class="home-photo-enter"><div class="home-photo-float"><div class="home-photo-tilt"><div class="home-photo-frame">
                        <img src="{{ asset('images/landing/'.$photo) }}" alt="{{ $alt }}" decoding="async" @if ($loop->first) fetchpriority="high" @endif>
                    </div></div></div></div></div>
                </div>
            @endforeach

            <div class="home-hero-copy">
                <img src="{{ asset('images/landing/logo pinjemin.svg') }}" alt="Logo Pinjemin" class="promo-hero-logo rounded-full object-cover landing-reveal" width="92" height="82">
                <p class="promo-hero-kicker landing-reveal" style="transition-delay: 40ms">Layanan penyewaan dan pengelolaan barang</p>
                <h1 class="landing-reveal" style="transition-delay: 80ms">Pinjemin<span class="text-accent">.</span></h1>
                <p class="promo-hero-tagline landing-reveal" style="transition-delay: 120ms">Sewa barang sesuai kebutuhan.<br>Kelola penyewaan dalam satu layanan.</p>
                <p class="promo-hero-description landing-reveal" style="transition-delay: 180ms">Pinjemin menghubungkan penyewa dengan pemilik barang untuk kebutuhan penggunaan sementara.</p>
                <div class="landing-reveal mt-6 flex flex-wrap justify-center gap-3" style="transition-delay: 240ms">
                    @if(config('features.marketplace_enabled'))
                        <a href="{{ route('browse') }}" class="btn btn-primary home-action">Menyewa Barang <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i></a>
                        <a href="{{ route('rent-out') }}" class="btn btn-secondary">Sewakan Barang</a>
                    @else
                    <a href="https://www.instagram.com/pinjemin911/" target="_blank" rel="noopener noreferrer" class="btn btn-primary home-action">
                        Ikuti @pinjemin911
                        <i data-lucide="arrow-up-right" class="h-4 w-4" aria-hidden="true"></i>
                    </a>
                    <a href="#tentang" class="btn btn-secondary">Tentang Pinjemin</a>
                    @endif
                </div>
            </div>
        </div>
        <a href="#tentang" class="home-hero-next absolute bottom-5 left-1/2 inline-flex -translate-x-1/2 items-center gap-2 whitespace-nowrap text-xs font-medium">Lihat selengkapnya <i data-lucide="chevron-down" class="h-4 w-4" aria-hidden="true"></i></a>
    </section>

    <section id="tentang" class="home-section scroll-mt-24 bg-white">
        <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-[0.85fr_1.15fr] lg:gap-20">
                <div class="landing-reveal">
                    <p class="eyebrow">Tentang Pinjemin</p>
                    <h2 class="home-heading mt-3">Penyewaan barang untuk kebutuhan sementara.</h2>
                </div>
                <div class="landing-reveal lg:pt-7" style="transition-delay: 80ms">
                    <p class="text-lg leading-8 text-zinc-700">Pinjemin menyediakan sarana bagi pemilik untuk menyewakan barang dan bagi penyewa untuk menemukan barang yang dibutuhkan.</p>
                    <p class="mt-5 text-sm leading-7 text-zinc-600">Penyewa dapat memperoleh barang sesuai kebutuhan. Pemilik dapat mengelola informasi barang, ketentuan sewa, dan pengajuan melalui Pinjemin.</p>
                </div>
            </div>

            <div class="promo-principles mt-12 grid md:grid-cols-3">
                @foreach ([
                    ['01', 'Penyewaan sesuai kebutuhan', 'Penuhi kebutuhan sementara tanpa harus selalu membeli barang baru.'],
                    ['02', 'Pencarian berdasarkan lokasi', 'Cari barang berdasarkan kategori, kota, kondisi, dan harga sewa.'],
                    ['03', 'Pengelolaan barang sewaan', 'Kelola harga, ketersediaan, dan persyaratan barang yang akan disewakan.'],
                ] as [$number, $title, $description])
                    <article class="landing-reveal promo-principle" style="transition-delay: {{ $loop->index * 70 }}ms">
                        <span>{{ $number }}</span>
                        <h3>{{ $title }}</h3>
                        <p>{{ $description }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="home-section promo-story-section overflow-hidden">
        <div class="mx-auto grid max-w-7xl gap-14 px-5 sm:px-6 lg:grid-cols-[0.94fr_1.06fr] lg:items-center lg:gap-20 lg:px-8">
            <div class="promo-deck-shell landing-reveal" data-promo-deck role="region" aria-roledescription="carousel" aria-label="Cerita Pinjemin">
                <div class="promo-deck-track" data-promo-track>
                    @foreach ([
                        ['promo-01.jpeg', 'Informasi layanan Pinjemin untuk penyewa dan pemilik barang.'],
                        ['promo-02.jpeg', 'Informasi penyewaan barang untuk kebutuhan penggunaan sementara.'],
                        ['promo-03.jpeg', 'Barang yang jarang digunakan dapat kembali bermanfaat melalui Pinjemin.'],
                        ['promo-04.jpeg', 'Pengenalan layanan penyewaan dan pengelolaan barang Pinjemin.'],
                    ] as [$poster, $alt])
                        <figure class="promo-deck-card" data-promo-card>
                            <img src="{{ asset('images/landing/'.$poster) }}" alt="{{ $alt }}" width="1080" height="1350" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async" draggable="false">
                        </figure>
                    @endforeach
                </div>

                <div class="promo-deck-controls" data-promo-controls>
                    <button type="button" class="promo-deck-button" data-promo-prev aria-label="Poster sebelumnya" title="Poster sebelumnya">
                        <i data-lucide="arrow-left" class="h-5 w-5" aria-hidden="true"></i>
                    </button>
                    <div class="promo-deck-dots" aria-label="Pilih poster">
                        @for ($index = 0; $index < 4; $index++)
                            <button type="button" data-promo-dot="{{ $index }}" aria-label="Tampilkan poster {{ $index + 1 }}"></button>
                        @endfor
                    </div>
                    <button type="button" class="promo-deck-button" data-promo-next aria-label="Poster berikutnya" title="Poster berikutnya">
                        <i data-lucide="arrow-right" class="h-5 w-5" aria-hidden="true"></i>
                    </button>
                </div>
                <p class="sr-only" data-promo-status aria-live="polite"></p>
            </div>

            <div class="landing-reveal" style="transition-delay: 80ms">
                <p class="eyebrow">Layanan Pinjemin</p>
                <h2 class="home-heading mt-3">Penuhi kebutuhan barang melalui penyewaan.</h2>
                <p class="mt-5 max-w-xl text-sm leading-7 text-zinc-600">Penyewa dapat mencari barang untuk penggunaan sementara. Pemilik dapat menawarkan barang layak pakai dan mengelola pengajuan penyewaan melalui layanan yang sama.</p>
                <div class="promo-category-list mt-8">
                    @foreach ([
                        'Akses barang sesuai kebutuhan dan durasi pemakaian',
                        'Pengelolaan penyewaan bagi pemilik barang',
                        'Informasi yang mudah dipahami oleh penyewa dan pemilik',
                        'Pilihan untuk beragam rencana, acara, dan proyek',
                    ] as $benefit)
                        <p><span aria-hidden="true"></span>{{ $benefit }}</p>
                    @endforeach
                </div>
                <p class="mt-5 text-xs font-medium text-zinc-500">Geser poster dengan mouse atau sentuhan untuk melihat cerita berikutnya.</p>
            </div>
        </div>
    </section>

    <section class="home-section bg-white">
        <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
            <div class="landing-reveal max-w-2xl">
                <p class="eyebrow">Layanan bagi penyewa dan pemilik</p>
                <h2 class="home-heading mt-3">Penyewaan dan pengelolaan barang dalam satu layanan.</h2>
            </div>
            <div class="home-audience mt-10 grid gap-8 lg:grid-cols-2 lg:gap-14">
                <div class="landing-reveal py-7">
                    <p class="text-sm font-semibold text-accent">Untuk penyewa</p>
                    <h3 class="mt-4 text-2xl font-semibold text-blue-950">Akses barang sesuai kebutuhan.</h3>
                    <p class="mt-3 max-w-xl text-sm leading-7 text-zinc-600">Temukan perlengkapan untuk perjalanan, acara, pekerjaan, atau kebutuhan lain yang hanya digunakan sesekali.</p>
                </div>
                <div class="landing-reveal py-7" style="transition-delay: 70ms">
                    <p class="text-sm font-semibold text-accent">Untuk pemilik</p>
                    <h3 class="mt-4 text-2xl font-semibold text-blue-950">Kelola barang yang akan disewakan.</h3>
                    <p class="mt-3 max-w-xl text-sm leading-7 text-zinc-600">Tentukan harga, persyaratan, dan ketersediaan barang, kemudian tinjau setiap pengajuan penyewaan.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="cara-kerja" class="home-section scroll-mt-24 promo-process-section">
        <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
            <div class="landing-reveal flex flex-col justify-between gap-5 md:flex-row md:items-end">
                <div>
                    <p class="eyebrow">Cara kerja Pinjemin</p>
                    <h2 class="home-heading mt-3">Dari mencari sampai mengembalikan.</h2>
                </div>
                <p class="max-w-md text-sm leading-7 text-zinc-600">Alur yang jelas membantu penyewa dan pemilik memahami setiap tahap penggunaan barang.</p>
            </div>

            <ol class="home-steps mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4" data-scroll-line>
                @foreach ([
                    ['Temukan', config('features.marketplace_enabled') ? 'Saring barang berdasarkan lokasi, periode sewa, dan jumlah unit yang Anda perlukan.' : 'Cari barang yang sesuai dengan kebutuhan, waktu, dan lokasi Anda.'],
                    ['Ajukan & bayar', config('features.marketplace_enabled') ? 'Ajukan periode sewa. Setelah disepakati, bayar ke Pinjemin dan unggah bukti untuk diperiksa administrator.' : 'Sampaikan kebutuhan dan sepakati detail penyewaan bersama pemilik.'],
                    ['Terima & gunakan', config('features.marketplace_enabled') ? 'Setelah lunas, periksa kondisi bersama pemilik dan gunakan barang sesuai kesepakatan.' : 'Terima lalu gunakan barang sesuai durasi dan kesepakatan.'],
                    ['Kembalikan', config('features.marketplace_enabled') ? 'Kembalikan barang, periksa kondisinya, dan selesaikan konfirmasi kedua pihak.' : 'Kembalikan barang dengan baik setelah kebutuhan selesai.'],
                ] as [$title, $description])
                    <li class="landing-reveal pt-5" style="transition-delay: {{ $loop->index * 70 }}ms">
                        <span class="promo-step-number">0{{ $loop->iteration }}</span>
                        <h3 class="mt-5 text-base font-semibold text-zinc-950">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-7 text-zinc-600">{{ $description }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section id="faq" class="home-section scroll-mt-24 promo-faq-section">
        <div class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-6 lg:grid-cols-[0.75fr_1.25fr] lg:gap-16 lg:px-8">
            <div class="landing-reveal">
                <p class="eyebrow">Pertanyaan umum</p>
                <h2 class="home-heading mt-3">Informasi layanan Pinjemin.</h2>
                <p class="mt-5 max-w-sm text-sm leading-7 text-zinc-600">Pelajari informasi layanan, ketentuan, serta prosedur penyewaan barang.</p>
                <a href="mailto:{{ config('assistant.email') }}" class="home-text-link mt-6">{{ config('assistant.email') }} <i data-lucide="arrow-up-right" class="h-4 w-4" aria-hidden="true"></i></a>
            </div>
            <div class="landing-reveal grid content-start gap-3" data-faq-group>
                @foreach (collect(config('assistant.topics'))->where('home_faq', true)
                    ->filter(fn ($topic) => (config('features.marketplace_enabled') || ($topic['public'] ?? false))
                        && (! ($topic['chat'] ?? false) || config('features.chat_enabled'))) as $topic)
                    <details class="home-faq overflow-hidden rounded-lg border border-blue-100 bg-white">
                        <summary class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-5 px-5 py-4 text-sm font-semibold text-zinc-950" aria-controls="faq-{{ $loop->index }}">{{ $topic['question'] }}<i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-blue-700 transition-transform" aria-hidden="true"></i></summary>
                        <div id="faq-{{ $loop->index }}" class="px-5 pb-5 text-sm leading-7 text-zinc-600">{{ $topic['answer'] }}</div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section id="kontak" class="promo-contact-band scroll-mt-24">
        <div class="mx-auto flex max-w-7xl flex-col gap-8 px-5 py-14 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <div class="landing-reveal">
                <p class="text-sm font-semibold text-white/70">Temukan Pinjemin</p>
                <h2 class="mt-2 max-w-2xl text-3xl font-semibold leading-tight text-white sm:text-4xl">Hubungi Pinjemin untuk informasi layanan.</h2>
            </div>
            <div class="landing-reveal flex flex-col gap-3 sm:flex-row">
                <a href="https://www.instagram.com/pinjemin911/" target="_blank" rel="noopener noreferrer" class="promo-contact-button promo-contact-button-primary">
                    @pinjemin911
                    <i data-lucide="arrow-up-right" class="h-4 w-4" aria-hidden="true"></i>
                </a>
                <a href="mailto:{{ config('assistant.email') }}" class="promo-contact-button promo-contact-button-secondary">Email Pinjemin</a>
            </div>
        </div>
    </section>
@endsection


