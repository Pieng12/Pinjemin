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
                    <input id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required autofocus class="input">
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <x-password-input />
                <label class="flex items-center gap-2 text-sm text-zinc-600">
                    <input type="checkbox" name="remember" value="1" class="rounded border-zinc-300 text-blue-700 focus:ring-blue-600">
                    Ingat saya
                </label>
                <label class="flex min-h-11 items-start gap-3 py-2 text-sm leading-6 text-zinc-600">
                    <input type="checkbox" name="terms_accepted" value="1" required @checked(old('terms_accepted')) class="mt-1 h-4 w-4 shrink-0 accent-brand" aria-describedby="login-terms-error">
                    <span>Saya menyetujui <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-blue-700 underline underline-offset-2">Syarat &amp; Ketentuan</a> serta <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-blue-700 underline underline-offset-2">Kebijakan Privasi</a> Pinjemin.</span>
                </label>
                <div id="login-terms-error">@error('terms_accepted')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
                <x-button type="submit" class="w-full">Masuk</x-button>
            </form>
            <div class="mt-5 flex flex-wrap gap-3 text-sm text-zinc-600">
                <a href="{{ route('register') }}" class="font-medium text-blue-700 hover:text-blue-800">Daftar akun</a>
            </div>
            <p class="mt-5 text-xs leading-6 text-zinc-500">Kenali aturan sebelum bertransaksi. <a href="{{ route('legal.terms') }}" class="font-semibold text-blue-700 underline underline-offset-2">Baca Syarat &amp; Ketentuan</a>.</p>
        </div>
    </section>
@endsection
