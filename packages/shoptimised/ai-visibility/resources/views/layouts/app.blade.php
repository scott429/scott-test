<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'AI shopping readiness' }}</title>
    <x-aiv::theme />
    @livewireStyles
    <style> body { margin: 0; background: var(--aiv-bg); } </style>
</head>
<body>
    {{ $slot }}
    @livewireScripts
</body>
</html>
