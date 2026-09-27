@extends('layouts.admin')

@section('title', 'Admin Dashboard - Pinjemin')

@section('content')
    <section class="page-shell">
        <div>
            <x-breadcrumb :items="['Admin' => null]" />
            <h1 class="mt-6 text-3xl font-semibold tracking-tight text-zinc-950">Admin Dashboard</h1>
            <p class="mt-2 text-zinc-600">Ringkasan operasional Pinjemin.</p>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-stat-card label="Total User" :value="$totalUsers" />
            <x-stat-card label="Total Barang" :value="$totalItems" />
            <x-stat-card label="Barang Aktif" :value="$activeItems" />
            <x-stat-card label="Total Booking" :value="$totalBookings" />
            <x-stat-card label="Perlu Tindakan" :value="$pendingBookings" />
        </div>

        <div class="mt-8 grid gap-4 md:grid-cols-2">
            <div class="card card-pad">
                <h2 class="font-semibold text-zinc-950">Katalog</h2>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">Kategori aktif</dt><dd class="font-semibold text-zinc-950">{{ $activeCategories }} / {{ $totalCategories }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">Barang tidak aktif</dt><dd class="font-semibold text-zinc-950">{{ $inactiveItems }}</dd></div>
                </dl>
            </div>
            <div class="card card-pad">
                <h2 class="font-semibold text-zinc-950">Booking</h2>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">Disetujui</dt><dd class="font-semibold text-zinc-950">{{ $approvedBookings }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">Dibatalkan</dt><dd class="font-semibold text-zinc-950">{{ $cancelledBookings }}</dd></div>
                </dl>
            </div>
        </div>
    </section>
@endsection
