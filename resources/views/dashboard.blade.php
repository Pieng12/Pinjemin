@extends('layouts.app')

@section('title', 'Ringkasan Akun - Pinjemin')

@section('content')
    <section class="page-shell">
        <div class="mx-auto max-w-4xl">
            <p class="eyebrow">Ringkasan Akun</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-950">Halo, {{ $user->name }}</h1>
            <p class="mt-2 text-zinc-600">Pilih area kerja sesuai aktivitas yang ingin kamu lakukan.</p>

            <div class="mt-8 grid gap-5 md:grid-cols-2">
                <a href="{{ route('my-bookings.index') }}" class="card card-pad transition hover:border-blue-200 hover:shadow-md">
                    <p class="text-sm font-semibold text-blue-700">Sebagai Penyewa</p>
                    <h2 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-950">Penyewaan Saya</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-600">Pantau permintaan dan riwayat penyewaan barangmu.</p>
                    <p class="mt-6 text-sm font-semibold text-zinc-950">{{ $rentalBookingsCount }} booking tercatat</p>
                </a>

                <a href="{{ route('owner.dashboard') }}" class="card card-pad transition hover:border-blue-200 hover:shadow-md">
                    <p class="text-sm font-semibold text-blue-700">Sebagai Pemilik</p>
                    <h2 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-950">Dashboard Pemilik</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-600">Kelola barang dan permintaan sewa dari workspace khusus pemilik.</p>
                    <p class="mt-6 text-sm font-semibold text-zinc-950">{{ $activeItemsCount }} barang aktif / {{ $pendingIncomingCount }} perlu tindakan</p>
                </a>
            </div>

            <div class="mt-6 card card-pad">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-950">Profil</h2>
                        <p class="mt-1 text-sm text-zinc-600">{{ $user->email }}{{ $user->city ? ' / '.$user->city : '' }}</p>
                    </div>
                    <x-button :href="route('profile.edit')" variant="outline">Edit Profil</x-button>
                </div>
            </div>
        </div>
    </section>
@endsection
