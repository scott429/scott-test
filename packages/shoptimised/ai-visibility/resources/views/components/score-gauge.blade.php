@props([
    'value' => 0,
    'max' => 100,
    'display' => null,
    'suffix' => '/ 100',
])
@php
    $maxVal = (float) ($max ?: 100);
    $v = max(0.0, min($maxVal, (float) $value));
    $arc = 238.76; // length of the semicircle path below
    $dash = round($v / $maxVal * $arc, 2);
    $shown = $display ?? (fmod($v, 1.0) === 0.0 ? (int) $v : round($v, 1));
@endphp
<svg viewBox="0 0 180 108" width="160" height="96" role="img"
     aria-label="Score {{ $shown }} {{ $suffix }}" {{ $attributes }}>
    <path d="M16,92 A76,76 0 0 1 164,92" fill="none" style="stroke: var(--aiv-border);"
          stroke-width="13" stroke-linecap="round" />
    <path d="M16,92 A76,76 0 0 1 164,92" fill="none" style="stroke: var(--aiv-primary);"
          stroke-width="13" stroke-linecap="round" stroke-dasharray="{{ $dash }} {{ $arc }}" />
    <text x="90" y="82" text-anchor="middle" style="fill: var(--aiv-text); font-family: var(--aiv-font);"
          font-size="32" font-weight="700">{{ $shown }}</text>
    <text x="90" y="100" text-anchor="middle" style="fill: var(--aiv-muted); font-family: var(--aiv-font);"
          font-size="12">{{ $suffix }}</text>
</svg>
