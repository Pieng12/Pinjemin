@extends('layouts.app')

@section('title', $title.' - Pinjemin')

@section('content')
    <section class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="rounded-md border border-dashed border-zinc-300 bg-white p-8">
            <p class="text-sm font-semibold uppercase text-blue-700">Tahap berikutnya</p>
            <h1 class="mt-3 text-3xl font-bold text-zinc-950">{{ $heading }}</h1>
            <p class="mt-3 leading-7 text-zinc-600">{{ $description }}</p>
            <a href="{{ route('home') }}" class="mt-6 inline-flex rounded-md border border-zinc-300 px-4 py-2.5 text-sm font-semibold hover:border-zinc-400">Kembali ke beranda</a>
        </div>
    </section>
@endsection
