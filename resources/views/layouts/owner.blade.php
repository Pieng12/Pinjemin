@php
    $workspaceRole = 'owner';
    $workspaceLabel = 'Pemilik';
    $workspaceAccountLabel = 'Pemilik barang';
    $workspaceHomeRoute = 'owner.dashboard';
    $workspaceDefaultTitle = 'Dasbor Pemilik - Pinjemin';
    $workspaceDescription = 'Ruang kerja pemilik untuk mengelola barang, pemesanan, dan pencairan.';
    $workspacePageTitle = match (true) {
        request()->routeIs('owner.dashboard') => 'Ringkasan',
        request()->routeIs('owner.payouts') => 'Pencairan Saya',
        request()->routeIs('identity.show') => 'Detail Verifikasi Identitas',
        request()->routeIs('identity.*') => 'Verifikasi Identitas',
        request()->routeIs('my-items.create') => 'Tambah Barang',
        request()->routeIs('my-items.edit') => 'Edit Barang',
        request()->routeIs('my-items.show') => 'Detail Barang',
        request()->routeIs('my-items.*') => 'Barang Saya',
        request()->routeIs('incoming-bookings.show') => 'Detail Pemesanan',
        request()->routeIs('payment-methods.*') => 'Rekening Pencairan',
        default => 'Pemesanan Masuk',
    };
    $workspaceNavigation = [
        ['owner.dashboard', 'Ringkasan', 'layout-dashboard', request()->routeIs('owner.dashboard')],
        ['my-items.index', 'Barang Saya', 'package', request()->routeIs('my-items.*') && !request()->routeIs('my-items.create')],
        ['incoming-bookings.index', 'Pemesanan Masuk', 'inbox', request()->routeIs('incoming-bookings.*')],
        ...(config('features.chat_enabled') ? [['chat.index', 'Chat', 'message-circle', request()->routeIs('chat.*')]] : []),
        ['payment-methods.index', 'Rekening Pencairan', 'credit-card', request()->routeIs('payment-methods.*')],
        ['owner.payouts', 'Pencairan Saya', 'wallet', request()->routeIs('owner.payouts')],
        ['identity.index', 'Verifikasi Identitas', 'badge-check', request()->routeIs('identity.*')],
        ['my-items.create', 'Tambah Barang', 'plus', request()->routeIs('my-items.create')],
    ];
@endphp

@extends('layouts.workspace')
