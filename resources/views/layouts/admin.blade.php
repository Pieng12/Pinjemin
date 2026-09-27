<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description', 'Admin Pinjemin')">
    <link rel="icon" type="image/png" href="{{ asset('images/landing/logo-pinjemin.png') }}">
    <title>@yield('title', 'Admin Pinjemin')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-[250px_1fr]">
        <aside class="hidden border-r border-zinc-200 bg-white lg:flex lg:min-h-screen lg:flex-col">
            <div class="p-6">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 text-lg font-semibold text-zinc-950">
                    <img src="{{ asset('images/landing/logo-pinjemin.png') }}" alt="" class="h-10 w-10 object-contain" width="40" height="36">
                    Pinjemin<span class="text-accent">.</span>
                </a>
                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-zinc-500">Admin Panel</p>
            </div>
            <nav class="flex flex-1 flex-col gap-1 px-3 text-sm font-medium" aria-label="Navigasi admin">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost justify-start">Dashboard</a>
                <a href="{{ route('admin.users.index') }}" class="btn btn-ghost justify-start">User</a>
                <a href="{{ route('admin.categories.index') }}" class="btn btn-ghost justify-start">Kategori</a>
                <a href="{{ route('admin.items.index') }}" class="btn btn-ghost justify-start">Barang</a>
                <a href="{{ route('admin.bookings.index') }}" class="btn btn-ghost justify-start">Booking</a>
            </nav>
            <div class="grid gap-2 border-t border-zinc-100 p-4">
                <a href="{{ route('home') }}" class="btn btn-outline justify-start">Kembali ke Marketplace</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-danger w-full justify-start">Keluar</button>
                </form>
            </div>
        </aside>

        <div id="admin-drawer" class="fixed inset-0 z-50 hidden bg-zinc-950/30 lg:hidden" data-open="false">
            <div class="flex h-full w-72 flex-col bg-white p-4 shadow-xl">
                <div class="flex items-center justify-between">
                    <p class="font-semibold text-zinc-950">Admin Panel</p>
                    <button type="button" data-mobile-menu-toggle="#admin-drawer" class="btn btn-outline px-3">Tutup</button>
                </div>
                <nav class="mt-6 grid gap-1 text-sm font-medium">
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost justify-start">Dashboard</a>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost justify-start">User</a>
                    <a href="{{ route('admin.categories.index') }}" class="btn btn-ghost justify-start">Kategori</a>
                    <a href="{{ route('admin.items.index') }}" class="btn btn-ghost justify-start">Barang</a>
                    <a href="{{ route('admin.bookings.index') }}" class="btn btn-ghost justify-start">Booking</a>
                </nav>
                <div class="mt-auto grid gap-2 border-t border-zinc-100 pt-4">
                    <a href="{{ route('home') }}" class="btn btn-outline justify-start">Kembali ke Marketplace</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-danger w-full justify-start">Keluar</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="min-w-0">
            <header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/90 backdrop-blur-xl lg:hidden">
                <div class="flex min-h-16 items-center justify-between px-4">
                    <button type="button" data-mobile-menu-toggle="#admin-drawer" aria-expanded="false" class="btn btn-outline px-3">Menu</button>
                    <p class="font-semibold text-zinc-950">Admin</p>
                    <a href="{{ route('home') }}" class="text-sm font-semibold text-blue-700">Marketplace</a>
                </div>
            </header>
            <main>
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
        </div>
    </div>
    <x-confirm-modal />
    @stack('scripts')
</body>
</html>
