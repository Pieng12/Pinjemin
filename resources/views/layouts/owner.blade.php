@php
    $workspaceRole = 'owner';
    $workspaceLabel = 'Pemilik';
    $workspaceAccountLabel = 'Pemilik barang';
    $workspaceHomeRoute = 'owner.dashboard';
    $workspaceDefaultTitle = 'Dashboard Pemilik - Pinjemin';
    $workspaceDescription = 'Workspace pemilik Pinjemin untuk mengelola barang dan permintaan sewa.';
    $workspacePageTitle = match (true) {
        request()->routeIs('owner.dashboard') => 'Ringkasan',
        request()->routeIs('owner.payouts') => 'Pencairan Saya',
        request()->routeIs('identity.show') => 'Detail Verifikasi Identitas',
        request()->routeIs('identity.*') => 'Verifikasi Identitas',
        request()->routeIs('my-items.create') => 'Tambah Barang',
        request()->routeIs('my-items.edit') => 'Edit Barang',
        request()->routeIs('my-items.show') => 'Detail Barang',
        request()->routeIs('my-items.*') => 'Barang Saya',
        request()->routeIs('incoming-bookings.show') => 'Detail Booking',
        request()->routeIs('payment-methods.*') => 'Metode Pembayaran',
        default => 'Booking Masuk',
    };
    $workspaceNavigation = [
        ['owner.dashboard', 'Ringkasan', 'layout-dashboard', request()->routeIs('owner.dashboard')],
        ['my-items.index', 'Barang Saya', 'package', request()->routeIs('my-items.*') && !request()->routeIs('my-items.create')],
        ['incoming-bookings.index', 'Booking Masuk', 'inbox', request()->routeIs('incoming-bookings.*')],
        ['payment-methods.index', 'Metode Pembayaran', 'credit-card', request()->routeIs('payment-methods.*')],
        ['owner.payouts', 'Pencairan Saya', 'wallet', request()->routeIs('owner.payouts')],
        ['identity.index', 'Verifikasi Identitas', 'badge-check', request()->routeIs('identity.*')],
        ['my-items.create', 'Tambah Barang', 'plus', request()->routeIs('my-items.create')],
    ];
@endphp

@extends('layouts.workspace')
