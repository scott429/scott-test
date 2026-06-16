@props(['label' => '', 'value' => '', 'tone' => null])
@php
    $toneColor = match ($tone) {
        'ok' => 'var(--aiv-ok-fg)',
        'warn' => 'var(--aiv-med-fg)',
        'bad' => 'var(--aiv-high-fg)',
        default => null,
    };
@endphp
<div class="aiv-metric">
    <div class="aiv-metric-label">{{ $label }}</div>
    <div class="aiv-metric-value" @if ($toneColor) style="color: {{ $toneColor }};" @endif>{{ $value }}</div>
</div>
