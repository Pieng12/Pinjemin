@php
    $ownerPageTitle = match (true) {
        request()->routeIs('owner.dashboard') => 'Overview',
        request()->routeIs('my-items.create') => 'Tambah Barang',
        request()->routeIs('my-items.edit') => 'Edit Barang',
        request()->routeIs('my-items.show') => 'Detail Barang',
        request()->routeIs('my-items.*') => 'Barang Saya',
        request()->routeIs('incoming-bookings.show') => 'Detail Booking',
        request()->routeIs('payment-methods.*') => 'Metode Pembayaran',
        default => 'Booking Masuk',
    };
    $ownerNavigation = [
        ['owner.dashboard', 'Overview', 'layout-dashboard', request()->routeIs('owner.dashboard')],
        ['my-items.index', 'Barang Saya', 'package', request()->routeIs('my-items.*') && !request()->routeIs('my-items.create')],
        ['incoming-bookings.index', 'Booking Masuk', 'inbox', request()->routeIs('incoming-bookings.*')],
        ['payment-methods.index', 'Metode Pembayaran', 'credit-card', request()->routeIs('payment-methods.*')],
        ['my-items.create', 'Tambah Barang', 'plus', request()->routeIs('my-items.create')],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description', 'Workspace pemilik Pinjemin untuk mengelola barang dan permintaan sewa.')">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/landing/logo pinjemin.svg') }}">
    <title>@yield('title', 'Dashboard Pemilik - Pinjemin')</title>
    <script>
        try {
            document.documentElement.dataset.ownerCollapsed = localStorage.getItem('pinjemin.ownerSidebarCollapsed') === 'true' ? 'true' : 'false';
        } catch {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased">
    <noscript><style>
        .owner-collapse-button, [data-owner-open], [data-owner-close] { display: none !important; }
        @media (max-width: 1023px) {
            .owner-sidebar { position: static; width: 100%; height: auto; visibility: visible; transform: none; }
            .owner-sidebar-nav { overflow: visible; }
        }
    </style></noscript>
    <div class="owner-workspace" data-owner-workspace>
        <button type="button" class="owner-sidebar-backdrop" data-owner-backdrop hidden aria-label="Tutup navigasi pemilik" tabindex="-1"></button>
        <aside id="owner-sidebar" class="owner-sidebar" data-owner-sidebar aria-label="Sidebar pemilik">
            <div class="owner-sidebar-head">
                <a href="{{ route('owner.dashboard') }}" class="owner-wordmark" aria-label="Pinjemin, dashboard pemilik">
                    <img src="{{ asset('images/landing/logo pinjemin.svg') }}" alt="" class="owner-logo-mark rounded-full object-cover" width="38" height="34">
                    <span>Pinjemin<span class="text-accent">.</span></span>
                </a>
                <button type="button" class="owner-icon-button owner-collapse-button" data-owner-collapse aria-controls="owner-sidebar" aria-expanded="true" aria-label="Ciutkan sidebar" title="Ciutkan sidebar">
                    <i data-lucide="panel-left-close" data-collapse-icon class="h-5 w-5" aria-hidden="true"></i>
                    <i data-lucide="panel-left-open" data-expand-icon hidden class="h-5 w-5" aria-hidden="true"></i>
                </button>
                <button type="button" class="owner-icon-button lg:hidden" data-owner-close aria-label="Tutup navigasi pemilik" title="Tutup navigasi pemilik"><i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i></button>
            </div>
            <p class="owner-sidebar-caption">Workspace pemilik</p>
            <nav class="owner-sidebar-nav" aria-label="Navigasi pemilik">
                @foreach ($ownerNavigation as [$routeName, $label, $icon, $active])
                    <a href="{{ route($routeName) }}" class="owner-nav-link {{ $active ? 'is-active' : '' }} {{ $routeName === 'my-items.create' ? 'owner-nav-create' : '' }}" aria-label="{{ $label }}" title="{{ $label }}" @if ($active) aria-current="page" @endif>
                        <i data-lucide="{{ $icon }}" class="h-5 w-5 shrink-0" aria-hidden="true"></i>
                        <span class="owner-nav-label">{{ $label }}</span>
                    </a>
                @endforeach
            </nav>
            <div class="owner-sidebar-footer">
                <div class="owner-sidebar-account">
                    <x-user-avatar :user="auth()->user()" />
                    <div class="owner-account-copy"><p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p><p class="text-xs text-zinc-500">Pemilik barang</p></div>
                </div>
                <a href="{{ route('browse') }}" class="owner-nav-link owner-marketplace-link" title="Kembali ke Marketplace" aria-label="Kembali ke Marketplace"><i data-lucide="arrow-left" class="h-5 w-5 shrink-0" aria-hidden="true"></i><span class="owner-nav-label">Kembali ke Marketplace</span></a>
            </div>
        </aside>

        <div class="owner-content">
            <header class="owner-topbar">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" class="owner-icon-button lg:hidden" data-owner-open aria-controls="owner-sidebar" aria-expanded="false" aria-label="Buka navigasi pemilik" title="Buka navigasi pemilik"><i data-lucide="menu" class="h-5 w-5" aria-hidden="true"></i></button>
                    <span class="hidden text-xs font-medium text-zinc-500 sm:block">Mode Pemilik</span>
                    <span class="hidden h-4 border-l border-zinc-200 sm:block" aria-hidden="true"></span>
                    <p class="truncate text-sm font-semibold text-zinc-900">{{ $ownerPageTitle }}</p>
                </div>
                <a href="{{ route('browse') }}" class="owner-topbar-marketplace" title="Kembali ke Marketplace"><span class="hidden sm:inline">Marketplace</span><i data-lucide="arrow-up-right" class="h-4 w-4" aria-hidden="true"></i><span class="sr-only sm:hidden">Kembali ke Marketplace</span></a>
            </header>

            <main id="owner-main">
                @if (session('status'))
                    <div class="page-shell pb-0"><div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div></div>
                @endif
                @if ($errors->any())
                    <div class="page-shell pb-0">
                        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                            <p class="font-semibold">Periksa kembali data yang kamu masukkan.</p>
                            <p class="mt-1">{{ $errors->first() }}</p>
                        </div>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    <x-confirm-modal />
    @stack('scripts')
</body>
</html>
