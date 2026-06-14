@props(['status' => ''])
@php
    $class = match ($status) {
        'completed', 'accepted' => 'is-ok',
        'failed', 'cancelled', 'rejected' => 'is-high',
        'running', 'queued', 'in_progress' => 'is-medium',
        default => 'is-low',
    };
@endphp
<span class="aiv-badge {{ $class }}">{{ str_replace('_', ' ', $status) }}</span>
