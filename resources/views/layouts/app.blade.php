@php($transparentNav = trim($__env->yieldContent('transparent_nav')) === 'true')
@php($navTitle = trim($__env->yieldContent('nav_title')))
@php($marketplaceEnabled = config('features.marketplace_enabled'))

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description', 'Pinjemin mempertemukan kebutuhan sementara dengan barang yang jarang digunakan di sekitar kita.')">
    <title>@yield('title', 'Pinjemin')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans text-zinc-900 antialiased">
    <div class="flex min-h-screen flex-col">
        <header data-site-header @if ($transparentNav) data-transparent-header @endif class="site-header {{ $transparentNav ? 'fixed' : 'sticky' }}">
            <nav class="site-nav-surface" aria-label="Navigasi utama">
                <div class="site-nav-row {{ $navTitle ? 'has-page-title' : '' }}">
                    <div class="site-nav-leading">
                        <a href="{{ route('home') }}" class="site-logo" aria-label="Pinjemin, kembali ke beranda">
                            <img src="{{ asset('images/landing/logo-pinjemin.png') }}" alt="" class="site-logo-mark" width="44" height="40">
                            <span>Pinjemin<span class="text-accent">.</span></span>
                        </a>

                        <div class="site-section-nav" data-nav-group>
                            <span class="site-nav-indicator" data-nav-indicator aria-hidden="true"></span>
                            <a href="{{ route('home') }}" class="site-nav-link {{ request()->routeIs('home') ? 'is-active' : '' }}" data-section-link="beranda" @if (request()->routeIs('home')) aria-current="location" @endif>Beranda</a>
                            <a href="{{ route('home') }}#tentang" class="site-nav-link" data-section-link="tentang">Tentang</a>
                            <a href="{{ route('home') }}#cara-kerja" class="site-nav-link" data-section-link="cara-kerja">Cara Kerja</a>
                            <a href="{{ route('home') }}#faq" class="site-nav-link" data-section-link="faq">FAQ</a>
                            <a href="{{ route('home') }}#kontak" class="site-nav-link" data-section-link="kontak">Kontak</a>
                        </div>
                    </div>

                    @hasSection('nav_title')
                        <div class="site-page-title-desktop">
                            <p class="site-page-title">{{ $navTitle }}</p>
                        </div>
                    @endif

                    <div class="site-nav-actions">
                        @if($marketplaceEnabled)
                            @if ($navTitle !== 'Menyewa Barang')
                                <a href="{{ route('browse') }}" class="site-nav-link font-semibold">Menyewa Barang</a>
                            @endif
                            @if ($navTitle !== 'Sewakan Barang')
                                <a href="{{ route('rent-out') }}" class="site-nav-pill">Sewakan Barang</a>
                            @endif
                            @auth
                                <div class="relative" data-dropdown>
                                    <button type="button" data-dropdown-trigger aria-expanded="false" aria-controls="account-menu" class="site-avatar-button">
                                        <x-user-avatar :user="auth()->user()" />
                                        {{ \Illuminate\Support\Str::limit(auth()->user()->name, 14) }}
                                        <i data-lucide="chevron-down" class="h-4 w-4"></i>
                                    </button>
                                    <div id="account-menu" data-dropdown-menu class="absolute right-0 mt-3 hidden w-64 origin-top-right rounded-lg border border-zinc-200 bg-white p-2 text-zinc-900 shadow-xl">
                                        <div class="px-3 py-2">
                                            <p class="font-semibold text-zinc-950">{{ auth()->user()->name }}</p>
                                            <p class="truncate text-xs text-zinc-500">{{ auth()->user()->email }}</p>
                                        </div>
                                        <div class="my-1 border-t border-zinc-100"></div>
                                        <p class="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-zinc-400">Aktivitas Saya</p>
                                        <a href="{{ route('my-bookings.index') }}" class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-zinc-50">Penyewaan Saya</a>
                                        <div class="my-1 border-t border-zinc-100"></div>
                                        <p class="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-zinc-400">Untuk Pemilik</p>
                                        <a href="{{ route('owner.dashboard') }}" class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-zinc-50">Dashboard Pemilik</a>
                                        <a href="{{ route('my-items.index') }}" class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-zinc-50">Barang Saya</a>
                                        <a href="{{ route('incoming-bookings.index') }}" class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-zinc-50">Booking Masuk</a>
                                        <div class="my-1 border-t border-zinc-100"></div>
                                        <a href="{{ route('profile.edit') }}" class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-zinc-50">Profil</a>
                                        @if (auth()->user()->isAdmin())
                                            <a href="{{ route('admin.dashboard') }}" class="block rounded-md px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">Admin Panel</a>
                                        @endif
                                        <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-zinc-100 pt-1">
                                            @csrf
                                            <button type="submit" class="block w-full rounded-md px-3 py-2 text-left text-sm font-medium text-red-700 hover:bg-red-50">Keluar</button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                <a href="{{ route('login') }}" class="site-nav-link font-semibold">Masuk</a>
                            @endauth
                        @else
                            <a href="https://www.instagram.com/pinjemin911/" target="_blank" rel="noopener noreferrer" class="site-nav-pill">
                                Ikuti Instagram
                                <i data-lucide="arrow-up-right" class="h-4 w-4" aria-hidden="true"></i>
                            </a>
                        @endif
                    </div>

                    <button type="button" data-mobile-menu-toggle="#mobile-menu" aria-expanded="false" aria-controls="mobile-menu" class="site-menu-button" aria-label="Buka menu">
                        <i data-lucide="menu" class="h-5 w-5"></i>
                    </button>
                </div>

                @hasSection('nav_title')
                    <div class="site-page-title-mobile"><p class="site-page-title">{{ $navTitle }}</p></div>
                @endif

                <div id="mobile-menu" class="site-mobile-menu hidden {{ $navTitle ? 'has-page-title' : '' }}" data-open="false">
                    <a href="{{ route('home') }}" class="site-mobile-link" data-section-link="beranda">Beranda</a>
                    <a href="{{ route('home') }}#tentang" class="site-mobile-link" data-section-link="tentang">Tentang</a>
                    <a href="{{ route('home') }}#cara-kerja" class="site-mobile-link" data-section-link="cara-kerja">Cara Kerja</a>
                    <a href="{{ route('home') }}#faq" class="site-mobile-link" data-section-link="faq">FAQ</a>
                    <a href="{{ route('home') }}#kontak" class="site-mobile-link" data-section-link="kontak">Kontak</a>
                    @if($marketplaceEnabled)
                        <div class="my-2 border-t border-zinc-100"></div>
                        <a href="{{ route('browse') }}" class="site-mobile-link {{ request()->routeIs('browse') ? 'is-active' : '' }}" @if (request()->routeIs('browse')) aria-current="page" @endif>Menyewa Barang</a>
                        <a href="{{ route('rent-out') }}" class="site-mobile-link {{ request()->routeIs('rent-out') ? 'is-active' : '' }}" @if (request()->routeIs('rent-out')) aria-current="page" @endif>Sewakan Barang</a>
                        @auth
                            <div class="my-2 border-t border-zinc-100"></div>
                            <a href="{{ route('my-bookings.index') }}" class="site-mobile-link">Penyewaan Saya</a>
                            <a href="{{ route('owner.dashboard') }}" class="site-mobile-link">Dashboard Pemilik</a>
                            <a href="{{ route('profile.edit') }}" class="site-mobile-link">Profil</a>
                            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                                @csrf
                                <button type="submit" class="btn btn-danger w-full justify-start">Keluar</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="site-mobile-link">Masuk</a>
                        @endauth
                    @else
                        <a href="https://www.instagram.com/pinjemin911/" target="_blank" rel="noopener noreferrer" class="site-mobile-instagram">
                            Ikuti @pinjemin911
                            <i data-lucide="arrow-up-right" class="h-4 w-4" aria-hidden="true"></i>
                        </a>
                    @endif
                </div>
                @if ($transparentNav)
                    <div class="site-scroll-progress" aria-hidden="true"><span data-nav-progress></span></div>
                @endif
            </nav>
        </header>

        <main class="flex-1">
            @if (session('status'))
                <div class="page-shell pb-0">
                    <div class="reveal rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm">
                        {{ session('status') }}
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="page-shell pb-0">
                    <div class="reveal rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 shadow-sm">
                        Periksa kembali data yang kamu masukkan.
                    </div>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="site-footer">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 text-sm sm:px-6 md:grid-cols-4 lg:px-8">
                <div class="md:col-span-2">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-3 text-xl font-semibold text-white">
                        <img src="{{ asset('images/landing/logo-pinjemin.png') }}" alt="" class="h-12 w-12 object-contain" width="48" height="44">
                        Pinjemin<span class="text-accent">.</span>
                    </a>
                    <p class="mt-4 max-w-md leading-7 text-white/70">Cara yang lebih ringan untuk memakai barang seperlunya dan membuat barang yang jarang digunakan kembali bermanfaat.</p>
                </div>
                <div>
                    <p class="font-semibold text-white">Navigasi</p>
                    <div class="mt-3 grid gap-2">
                        <a href="{{ route('home') }}" class="hover:text-white">Beranda</a>
                        <a href="{{ route('home') }}#tentang" class="hover:text-white">Tentang</a>
                        <a href="{{ route('home') }}#cara-kerja" class="hover:text-white">Cara Kerja</a>
                        <a href="{{ route('home') }}#faq" class="hover:text-white">FAQ</a>
                        <a href="{{ route('home') }}#kontak" class="hover:text-white">Kontak</a>
                    </div>
                </div>
                <div>
                    <p class="font-semibold text-white">Temukan Kami</p>
                    <div class="mt-3 grid gap-2">
                        <a href="https://www.instagram.com/pinjemin911/" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 hover:text-white">@pinjemin911 <i data-lucide="arrow-up-right" class="h-4 w-4" aria-hidden="true"></i></a>
                        <a href="mailto:pinjemin99@gmail.com" class="hover:text-white">pinjemin99@gmail.com</a>
                    </div>
                </div>
            </div>
            <div class="border-t border-white/10 px-4 py-5 text-center text-xs text-white/55">
                &copy; {{ now()->year }} Pinjemin. Semua hak dilindungi.
                @stack('footer_credits')
            </div>
        </footer>
    </div>
    <x-confirm-modal />
    @stack('scripts')
</body>
</html>
