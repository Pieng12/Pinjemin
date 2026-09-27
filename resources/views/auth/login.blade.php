@extends('layouts.auth')

@section('title', 'Masuk - Pinjemin')

@section('content')
    <section class="flex flex-1 items-center justify-center py-12">
        <div class="card card-pad w-full max-w-md">
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-950">Masuk</h1>
            <p class="mt-2 text-sm text-zinc-600">Lanjutkan aktivitas sewa atau kelola barangmu.</p>
            <form method="POST" action="{{ route('login') }}" class="mt-6 flex flex-col gap-4">
                @csrf
                <div>
                    <label for="email" class="field-label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="input">
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="field-label">Password</label>
                    <input id="password" name="password" type="password" required class="input">
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-zinc-600">
                    <input type="checkbox" name="remember" value="1" class="rounded border-zinc-300 text-blue-700 focus:ring-blue-600">
                    Ingat saya
                </label>
                <x-button type="submit" class="w-full">Masuk</x-button>
            </form>
            <div class="mt-5 flex flex-wrap gap-3 text-sm text-zinc-600">
                <a href="{{ route('register') }}" class="font-medium text-blue-700 hover:text-blue-800">Daftar akun</a>
                <a href="{{ route('password.request') }}" class="font-medium text-blue-700 hover:text-blue-800">Lupa password?</a>
            </div>
        </div>
    </section>
@endsection
