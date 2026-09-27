@extends('layouts.admin')

@section('title', 'User - Admin Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="[
            'Admin' => route('admin.dashboard'),
            'User' => null,
        ]" />

        <div class="page-header mt-6">
            <div>
                <p class="eyebrow">Akun platform</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-zinc-950">User</h1>
                <p class="mt-2 text-zinc-600">Daftar akun pengguna Pinjemin.</p>
            </div>
        </div>

        <div class="mt-8 table-shell">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="table-head">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Kota</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($users as $user)
                            <tr class="table-row">
                                <td class="px-4 py-3 font-medium text-zinc-950">{{ $user->name }}</td>
                                <td class="px-4 py-3 break-all text-zinc-600">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    <span class="badge bg-zinc-100 text-zinc-700">{{ $user->role }}</span>
                                </td>
                                <td class="px-4 py-3 text-zinc-600">{{ $user->city ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10">
                                    <x-empty-state title="Belum ada user" description="Akun pengguna akan tampil di sini." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $users->links() }}</div>
    </section>
@endsection
