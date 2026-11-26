<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description', $workspaceDescription)">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/landing/logo pinjemin.svg') }}">
    <title>@yield('title', $workspaceDefaultTitle)</title>
    <script>
        try {
            const role = @js($workspaceRole);
            const collapsed = localStorage.getItem(`pinjemin.${role}SidebarCollapsed`) === 'true';
            document.documentElement.dataset.workspaceCollapsed = String(collapsed);
            document.documentElement.dataset[`${role}Collapsed`] = String(collapsed);
        } catch {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased">
    <div class="workspace owner-workspace" data-workspace data-workspace-role="{{ $workspaceRole }}" @if ($workspaceRole === 'owner') data-owner-workspace @endif>
        <a href="#{{ $workspaceRole }}-main" class="workspace-skip-link">Lewati ke konten utama</a>
        <button type="button" class="owner-sidebar-backdrop" data-workspace-backdrop @if ($workspaceRole === 'owner') data-owner-backdrop @endif hidden aria-label="Tutup navigasi {{ strtolower($workspaceLabel) }}" tabindex="-1"></button>
        <aside id="{{ $workspaceRole }}-sidebar" class="owner-sidebar" data-workspace-sidebar @if ($workspaceRole === 'owner') data-owner-sidebar @endif aria-label="Sidebar {{ strtolower($workspaceLabel) }}" tabindex="-1">
            <div class="owner-sidebar-head">
                <a href="{{ route($workspaceHomeRoute) }}" class="owner-wordmark" aria-label="Pinjemin, dashboard {{ strtolower($workspaceLabel) }}">
                    <img src="{{ asset('images/landing/logo pinjemin.svg') }}" alt="" class="owner-logo-mark rounded-full object-cover" width="38" height="34">
                    <span>Pinjemin<span class="text-accent">.</span></span>
                </a>
                <button type="button" class="owner-icon-button owner-collapse-button" data-workspace-collapse @if ($workspaceRole === 'owner') data-owner-collapse @endif aria-controls="{{ $workspaceRole }}-sidebar" aria-expanded="true" aria-label="Ciutkan sidebar" title="Ciutkan sidebar">
                    <i data-lucide="panel-left-close" data-collapse-icon class="h-5 w-5" aria-hidden="true"></i>
                    <i data-lucide="panel-left-open" data-expand-icon hidden class="h-5 w-5" aria-hidden="true"></i>
                </button>
                <button type="button" class="owner-icon-button lg:hidden" data-workspace-close @if ($workspaceRole === 'owner') data-owner-close @endif aria-label="Tutup navigasi {{ strtolower($workspaceLabel) }}" title="Tutup navigasi {{ strtolower($workspaceLabel) }}"><i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i></button>
            </div>
            <p class="owner-sidebar-caption">Ruang kerja {{ strtolower($workspaceLabel) }}</p>
            <nav class="owner-sidebar-nav" aria-label="Navigasi {{ strtolower($workspaceLabel) }}">
                @foreach ($workspaceNavigation as [$routeName, $label, $icon, $active])
                    <a href="{{ route($routeName) }}" data-workspace-route="{{ $routeName }}" class="owner-nav-link {{ $active ? 'is-active' : '' }} {{ $routeName === 'my-items.create' ? 'owner-nav-create' : '' }}" aria-label="{{ $label }}" title="{{ $label }}" @if ($active) aria-current="page" @endif>
                        <i data-lucide="{{ $icon }}" class="h-5 w-5 shrink-0" aria-hidden="true"></i>
                        <span class="owner-nav-label">{{ $label }}</span>
                    </a>
                @endforeach
            </nav>
            <div class="owner-sidebar-footer">
                <div class="owner-sidebar-account">
                    <x-user-avatar :user="auth()->user()" />
                    <div class="owner-account-copy"><p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p><p class="text-xs text-zinc-500">{{ $workspaceAccountLabel }}</p></div>
                </div>
                <a href="{{ route('profile.edit') }}" class="owner-nav-link" aria-label="Profil Saya" title="Profil Saya"><i data-lucide="user-check" class="h-5 w-5 shrink-0" aria-hidden="true"></i><span class="owner-nav-label">Profil Saya</span></a>
                <a href="{{ route('browse') }}" class="owner-nav-link owner-marketplace-link" title="Kembali ke Marketplace" aria-label="Kembali ke Marketplace"><i data-lucide="arrow-left" class="h-5 w-5 shrink-0" aria-hidden="true"></i><span class="owner-nav-label">Kembali ke Marketplace</span></a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="owner-nav-link workspace-logout w-full" aria-label="Keluar" title="Keluar"><i data-lucide="arrow-right" class="h-5 w-5 shrink-0" aria-hidden="true"></i><span class="owner-nav-label">Keluar</span></button>
                </form>
            </div>
        </aside>

        <div class="owner-content" data-workspace-content>
            <header class="owner-topbar">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" class="owner-icon-button lg:hidden" data-workspace-open @if ($workspaceRole === 'owner') data-owner-open @endif aria-controls="{{ $workspaceRole }}-sidebar" aria-expanded="false" aria-label="Buka navigasi {{ strtolower($workspaceLabel) }}" title="Buka navigasi {{ strtolower($workspaceLabel) }}"><i data-lucide="menu" class="h-5 w-5" aria-hidden="true"></i></button>
                    <span class="hidden text-xs font-medium text-zinc-500 sm:block">Mode {{ $workspaceLabel }}</span>
                    <span class="hidden h-4 border-l border-zinc-200 sm:block" aria-hidden="true"></span>
                    <p class="truncate text-sm font-semibold text-zinc-900">{{ $workspacePageTitle }}</p>
                </div>
                <a href="{{ route('browse') }}" class="owner-topbar-marketplace" title="Kembali ke Marketplace"><span class="hidden sm:inline">Marketplace</span><i data-lucide="arrow-up-right" class="h-4 w-4" aria-hidden="true"></i><span class="sr-only sm:hidden">Kembali ke Marketplace</span></a>
            </header>

            <main id="{{ $workspaceRole }}-main" tabindex="-1">
                @if (session('status'))
                    <div class="page-shell pb-0"><div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div></div>
                @endif
                @if (collect($errors->getBags())->contains(fn ($bag) => $bag->any()))
                    <div class="page-shell pb-0">
                        <x-validation-summary />
                    </div>
                @endif
                @yield('content')
            </main>
            <footer class="border-t border-zinc-200 px-4 py-5 text-zinc-500 sm:px-6 lg:px-8"><x-legal-links /></footer>
        </div>
    </div>
    <x-confirm-modal />
    <x-legal-modal />
    <x-assistant :context="$workspaceRole" />
    @stack('scripts')
</body>
</html>
