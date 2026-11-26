@extends('layouts.admin')

@section('title', 'Pengguna - Administrator Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="[
            'Administrator' => route('admin.dashboard'),
            'Pengguna' => null,
        ]" />

        <div class="page-header mt-6">
            <div>
                <p class="eyebrow">Akun platform</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-zinc-950">Pengguna</h1>
                <p class="mt-2 text-zinc-600">Daftar akun pengguna Pinjemin.</p>
            </div>
        </div>

        @php
            $filterSelects = ['role' => ['label' => 'Peran', 'all' => 'Semua peran', 'options' => $roles]];
        @endphp
        <x-list-filters :action="route('admin.users.index')" :filters="$filters" search-label="Cari pengguna" placeholder="Nama atau email" :selects="$filterSelects" />
        <x-list-results :results="$users" :total-count="$totalCount" :filters="$filters" :selects="$filterSelects" :action="route('admin.users.index')" empty-title="Belum ada pengguna" empty-description="Akun pengguna akan tampil di sini." />

        @if ($users->isNotEmpty())
            <div class="mt-8 table-shell">
                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm">
                        <thead class="table-head">
                            <tr>
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3">Email</th>
                                <th class="min-w-44 px-4 py-3 text-center">Peran</th>
                                <th class="px-4 py-3">Kota</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @foreach ($users as $user)
                                <tr class="table-row">
                                    <td class="px-4 py-3 font-medium text-zinc-950">{{ $user->name }}</td>
                                    <td class="px-4 py-3 break-all text-zinc-600">{{ $user->email }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="badge bg-zinc-100 text-zinc-700">{{ $roles[$user->role] ?? $user->role }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-zinc-600">{{ $user->city ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="divide-y divide-zinc-200 lg:hidden">
                    @foreach ($users as $user)
                        <article class="space-y-3 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <h2 class="min-w-0 break-words font-semibold text-zinc-950">{{ $user->name }}</h2>
                                <span class="badge bg-zinc-100 text-zinc-700">{{ $roles[$user->role] ?? $user->role }}</span>
                            </div>
                            <dl class="grid gap-2 text-sm">
                                <div><dt class="text-zinc-500">Email</dt><dd class="break-all text-zinc-700">{{ $user->email }}</dd></div>
                                <div><dt class="text-zinc-500">Kota</dt><dd class="text-zinc-700">{{ $user->city ?? '-' }}</dd></div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-6">{{ $users->links() }}</div>
    </section>
@endsection
