@props(['value' => 0])
<div class="aiv-bar"><div class="aiv-bar-fill" style="width: {{ max(0, min(100, (float) $value)) }}%"></div></div>
