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
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus class="input">
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="field-label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required class="input">
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="field-label">Password</label>
                    <input id="password" name="password" type="password" required class="input">
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="field-label">Konfirmasi Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required class="input">
                </div>
                <x-button type="submit" class="w-full">Daftar</x-button>
            </form>
            <p class="mt-5 text-sm text-zinc-600">Sudah punya akun? <a href="{{ route('login') }}" class="font-medium text-blue-700 hover:text-blue-800">Masuk</a></p>
        </div>
    </section>
@endsection
