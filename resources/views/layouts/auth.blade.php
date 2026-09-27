<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description', 'Masuk atau daftar ke Pinjemin.')">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/landing/logo pinjemin.svg') }}">
    <title>@yield('title', 'Pinjemin')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased">
    <main class="min-h-screen">
        <div class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-semibold text-zinc-950">
                    <img src="{{ asset('images/landing/logo pinjemin.svg') }}" alt="" class="h-10 w-10 rounded-full object-cover" width="40" height="36">
                    Pinjemin<span class="text-accent">.</span>
                </a>
                <a href="{{ route('home') }}" class="text-sm font-semibold text-blue-700">Kembali ke marketplace</a>
            </div>

            @if ($errors->any())
                <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 shadow-sm">
                    Periksa kembali data yang kamu masukkan.
                </div>
            @endif

            @yield('content')
        </div>
    </main>
    @stack('scripts')
</body>
</html>
