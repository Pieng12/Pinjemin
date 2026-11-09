@php
    $workspaceRole = 'admin';
    $workspaceLabel = 'Admin';
    $workspaceAccountLabel = 'Administrator';
    $workspaceHomeRoute = 'admin.dashboard';
    $workspaceDefaultTitle = 'Admin Pinjemin';
    $workspaceDescription = 'Workspace admin Pinjemin untuk mengelola marketplace.';
    $workspacePageTitle = match (true) {
        request()->routeIs('admin.dashboard') => 'Dashboard',
        request()->routeIs('admin.categories.create') => 'Tambah Kategori',
        request()->routeIs('admin.categories.edit') => 'Edit Kategori',
        request()->routeIs('admin.categories.*') => 'Kategori',
        request()->routeIs('admin.users.*') => 'Pengguna',
        request()->routeIs('admin.items.show') => 'Detail Barang',
        request()->routeIs('admin.items.*') => 'Barang',
        request()->routeIs('admin.bookings.show') => 'Detail Booking',
        request()->routeIs('admin.bookings.*') => 'Booking',
        request()->routeIs('admin.finance') => 'Keuangan & Pencairan',
        request()->routeIs('identity.show') => 'Detail Verifikasi Identitas',
        request()->routeIs('admin.identity.*') => 'Verifikasi Identitas',
        request()->routeIs('admin.payment-methods.*') => 'Rekening Pinjemin',
        default => 'Admin',
    };
    $workspaceNavigation = [
        ['admin.dashboard', 'Dashboard', 'layout-dashboard', request()->routeIs('admin.dashboard')],
        ['admin.users.index', 'Pengguna', 'user-check', request()->routeIs('admin.users.*')],
        ['admin.categories.index', 'Kategori', 'clipboard-check', request()->routeIs('admin.categories.*')],
        ['admin.items.index', 'Barang', 'package', request()->routeIs('admin.items.*')],
        ['admin.bookings.index', 'Booking', 'inbox', request()->routeIs('admin.bookings.*')],
        ['admin.finance', 'Keuangan & Pencairan', 'wallet', request()->routeIs('admin.finance')],
        ['admin.identity.index', 'Verifikasi Identitas', 'badge-check', request()->routeIs('admin.identity.*', 'identity.show')],
        ['admin.payment-methods.index', 'Rekening Pinjemin', 'credit-card', request()->routeIs('admin.payment-methods.*')],
    ];
@endphp

@extends('layouts.workspace')
