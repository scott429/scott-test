<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'AI shopping readiness' }} · {{ config('app.name', 'Shoptimised') }}</title>

    {{-- Host app base styles (resets/tokens), same-origin via Vite. --}}
    @vite(['resources/css/app.css'])

    {{-- Shoptimised brand font. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- Package content styling (.aiv-* classes used by the report views). --}}
    <x-aiv::theme />
    @livewireStyles

    <style>
        :root {
            --host-fg: #081836; --host-muted: #6b7a93;
            --host-border: rgba(8, 24, 54, .08); --host-surface: #f5f7fa;
            --host-brand: #1fb788;
            --host-font: 'Baloo 2', ui-rounded, 'Segoe UI', system-ui, sans-serif;
        }
        body { margin: 0; background: var(--aiv-bg); color: var(--host-fg); font-family: var(--host-font); }
        .aiv-wrap { font-family: var(--host-font); }
        .aiv-topbar { background: #fff; border-bottom: 1px solid var(--host-border); }
        .aiv-topbar-inner { max-width: 1040px; margin: 0 auto; padding: 0 1rem; height: 60px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .aiv-brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 16px; color: var(--host-fg); text-decoration: none; white-space: nowrap; }
        .aiv-brand-mark { width: 30px; height: 30px; border-radius: 9px; background: var(--host-brand); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; }
        .aiv-nav { display: flex; align-items: center; gap: 2px; flex: 1; }
        .aiv-navlink { font-size: 14px; font-weight: 500; color: var(--host-muted); text-decoration: none; padding: 7px 11px; border-radius: 8px; white-space: nowrap; }
        .aiv-navlink:hover { background: var(--host-surface); color: var(--host-fg); }
        .aiv-navlink.is-active { color: #0f7a58; background: var(--aiv-ok-bg); }
        .aiv-user { display: flex; align-items: center; gap: 10px; }
        .aiv-username { font-size: 13px; color: var(--host-muted); white-space: nowrap; }
        .aiv-logout { background: none; border: 1px solid var(--host-border); color: var(--host-fg); font: inherit; font-weight: 500; font-size: 13px; padding: 6px 12px; border-radius: 8px; cursor: pointer; }
        .aiv-logout:hover { background: var(--host-surface); }
        .aiv-footer { max-width: 1040px; margin: 0 auto; padding: 1.5rem 1rem 3rem; color: var(--host-muted); font-size: 12px; }
    </style>
</head>
<body>
    <header class="aiv-topbar">
        <div class="aiv-topbar-inner">
            <a href="{{ url('/dashboard') }}" class="aiv-brand">
                <span class="aiv-brand-mark" aria-hidden="true">{{ strtoupper(substr(config('app.name', 'S'), 0, 1)) }}</span>
                {{ config('app.name', 'Shoptimised') }}
            </a>
            <nav class="aiv-nav">
                <a href="{{ url('/dashboard') }}" class="aiv-navlink">Dashboard</a>
                <a href="{{ route('aiv.feeds') }}" class="aiv-navlink {{ request()->routeIs('aiv.feeds') || request()->routeIs('aiv.feeds.show') ? 'is-active' : '' }}">Feeds</a>
                <a href="{{ route('aiv.landing') }}" class="aiv-navlink {{ request()->routeIs('aiv.landing') || request()->routeIs('aiv.batches.*') || request()->routeIs('aiv.new') || request()->routeIs('aiv.groups.*') ? 'is-active' : '' }}">AI shopping readiness</a>
                <a href="{{ route('aiv.qna') }}" class="aiv-navlink {{ request()->routeIs('aiv.qna') ? 'is-active' : '' }}">Q&amp;A insights</a>
            </nav>
            @auth
                <div class="aiv-user">
                    <span class="aiv-username">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="aiv-logout">Log out</button>
                    </form>
                </div>
            @endauth
        </div>
    </header>

    {{ $slot }}

    <footer class="aiv-footer">
        Monitored AI visibility · controlled prompt testing. &copy; {{ date('Y') }} {{ config('app.name', 'Shoptimised') }}.
    </footer>

    @livewireScripts
</body>
</html>
