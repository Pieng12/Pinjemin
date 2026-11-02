@extends('layouts.auth')

@section('title', 'Daftar - Pinjemin')

@section('content')
    <section class="flex flex-1 items-center justify-center py-12">
        <div class="card card-pad w-full max-w-md">
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-950">Daftar</h1>
            <p class="mt-2 text-sm text-zinc-600">Satu akun untuk menyewa barang dan mengelola barang milikmu.</p>
            <form method="POST" action="{{ route('register') }}" class="mt-6 flex flex-col gap-4">
                @csrf
                <div>
                    <label for="name" class="field-label">Nama</label>
                    <input id="name" name="name" type="text" autocomplete="name" value="{{ old('name') }}" required autofocus class="input">
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="field-label">Email</label>
                    <input id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required class="input">
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <x-password-input autocomplete="new-password" minlength="8" help="Gunakan minimal 8 karakter." />
                <x-password-input name="password_confirmation" label="Konfirmasi Password" autocomplete="new-password" minlength="8" />
                <label class="flex min-h-11 items-start gap-3 py-2 text-sm leading-6 text-zinc-600">
                    <input type="checkbox" name="terms_accepted" value="1" required @checked(old('terms_accepted')) class="mt-1 h-4 w-4 shrink-0 accent-brand" aria-describedby="terms-error">
                    <span>Saya menyetujui <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-blue-700 underline underline-offset-2">Syarat &amp; Ketentuan</a> serta <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-blue-700 underline underline-offset-2">Kebijakan Privasi</a> Pinjemin.</span>
                </label>
                <div id="terms-error">@error('terms_accepted')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
                <x-button type="submit" class="w-full">Daftar</x-button>
            </form>
            <p class="mt-5 text-sm text-zinc-600">Sudah punya akun? <a href="{{ route('login') }}" class="font-medium text-blue-700 hover:text-blue-800">Masuk</a></p>
        </div>
    </section>
@endsection
