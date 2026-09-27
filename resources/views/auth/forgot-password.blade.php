@extends('layouts.auth')

@section('title', 'Lupa Password - Pinjemin')

@section('content')
    <section class="page-shell max-w-md py-16">
        <div class="card card-pad">
            <p class="eyebrow">Bantuan akun</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-zinc-950">Lupa password</h1>
            <p class="mt-3 text-sm leading-6 text-zinc-600">Reset password belum diaktifkan karena project belum memakai starter kit auth atau konfigurasi email. Fitur ini bisa ditambahkan saat alur email siap.</p>
            <x-button href="{{ route('login') }}" class="mt-6">Kembali ke login</x-button>
        </div>
    </section>
@endsection
