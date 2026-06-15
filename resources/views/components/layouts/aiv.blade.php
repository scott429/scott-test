<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'AI shopping readiness' }} · {{ config('app.name', 'Shoptimised') }}</title>

    {{-- Host app fonts (Instrument Sans) + theme tokens, same-origin via Vite. --}}
    @vite(['resources/css/app.css'])

    {{-- Package content styling (.aiv-* classes used by the report views). --}}
    <x-aiv::theme />
    @livewireStyles

    <style>
        :root {
            --host-bg: #ffffff; --host-fg: #0a0a0a; --host-muted: #737373;
            --host-border: #ececec; --host-surface: #fafafa;
            --host-font: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, sans-serif;
        }
        body { margin: 0; background: var(--aiv-bg); color: var(--host-fg); font-family: var(--host-font); }
        .aiv-wrap { font-family: var(--host-font); }
        .aiv-topbar { background: var(--host-bg); border-bottom: 1px solid var(--host-border); }
        .aiv-topbar-inner { max-width: 1040px; margin: 0 auto; padding: 0 1rem; height: 56px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .aiv-brand { display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 15px; color: var(--host-fg); text-decoration: none; white-space: nowrap; }
        .aiv-brand-mark { width: 28px; height: 28px; border-radius: 7px; background: var(--host-fg); color: var(--host-bg); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; }
        .aiv-nav { display: flex; align-items: center; gap: 4px; flex: 1; }
        .aiv-navlink { font-size: 14px; color: var(--host-muted); text-decoration: none; padding: 7px 10px; border-radius: 7px; white-space: nowrap; }
        .aiv-navlink:hover { background: var(--host-surface); color: var(--host-fg); }
        .aiv-navlink.is-active { color: var(--host-fg); background: var(--host-surface); }
        .aiv-user { display: flex; align-items: center; gap: 10px; }
        .aiv-username { font-size: 13px; color: var(--host-muted); white-space: nowrap; }
        .aiv-logout { background: none; border: 1px solid var(--host-border); color: var(--host-fg); font: inherit; font-size: 13px; padding: 6px 12px; border-radius: 7px; cursor: pointer; }
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
