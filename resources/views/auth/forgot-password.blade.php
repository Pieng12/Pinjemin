@extends('layouts.auth')

@section('title', 'Lupa Kata Sandi - Pinjemin')

@section('content')
    <section class="page-shell max-w-md py-16">
        <div class="card card-pad">
            <p class="eyebrow">Bantuan akun</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-zinc-950">Lupa kata sandi</h1>
            <p class="mt-3 text-sm leading-6 text-zinc-600">Hubungi <a href="mailto:{{ config('assistant.email') }}" class="font-semibold text-brand-700">{{ config('assistant.email') }}</a> untuk bantuan akses akun. Sertakan alamat email akun Anda dan jaga kerahasiaan kata sandi serta kode verifikasi.</p>
            <x-button href="{{ route('login') }}" class="mt-6">Kembali ke Halaman Masuk</x-button>
        </div>
    </section>
@endsection
