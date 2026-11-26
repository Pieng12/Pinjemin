@php
    $workspaceRole = 'admin';
    $workspaceLabel = 'Administrator';
    $workspaceAccountLabel = 'Administrator';
    $workspaceHomeRoute = 'admin.dashboard';
    $workspaceDefaultTitle = 'Administrator Pinjemin';
    $workspaceDescription = 'Ruang Kerja administrator Pinjemin untuk mengelola marketplace.';
    $workspacePageTitle = match (true) {
        request()->routeIs('admin.dashboard') => 'Dasbor',
        request()->routeIs('admin.categories.create') => 'Tambah Kategori',
        request()->routeIs('admin.categories.edit') => 'Edit Kategori',
        request()->routeIs('admin.categories.*') => 'Kategori',
        request()->routeIs('admin.users.*') => 'Pengguna',
        request()->routeIs('admin.items.show') => 'Detail Barang',
        request()->routeIs('admin.items.*') => 'Barang',
        request()->routeIs('admin.bookings.show') => 'Detail Pemesanan',
        request()->routeIs('admin.bookings.*') => 'Pemesanan',
        request()->routeIs('admin.finance') => 'Keuangan & Pencairan',
        request()->routeIs('admin.chat-reports.*') => 'Laporan Pesan',
        request()->routeIs('identity.show') => 'Detail Verifikasi Identitas',
        request()->routeIs('admin.identity.*') => 'Verifikasi Identitas',
        request()->routeIs('admin.payment-methods.*') => 'Rekening Pinjemin',
        default => 'Administrator',
    };
    $workspaceNavigation = [
        ['admin.dashboard', 'Dasbor', 'layout-dashboard', request()->routeIs('admin.dashboard')],
        ['admin.users.index', 'Pengguna', 'user-check', request()->routeIs('admin.users.*')],
        ['admin.categories.index', 'Kategori', 'clipboard-check', request()->routeIs('admin.categories.*')],
        ['admin.items.index', 'Barang', 'package', request()->routeIs('admin.items.*')],
        ['admin.bookings.index', 'Pemesanan', 'inbox', request()->routeIs('admin.bookings.*')],
        ...(config('features.chat_enabled') ? [['admin.chat-reports.index', 'Laporan Pesan', 'message-circle', request()->routeIs('admin.chat-reports.*')]] : []),
        ['admin.finance', 'Keuangan & Pencairan', 'wallet', request()->routeIs('admin.finance')],
        ['admin.identity.index', 'Verifikasi Identitas', 'badge-check', request()->routeIs('admin.identity.*', 'identity.show')],
        ['admin.payment-methods.index', 'Rekening Pinjemin', 'credit-card', request()->routeIs('admin.payment-methods.*')],
    ];
@endphp

@extends('layouts.workspace')
