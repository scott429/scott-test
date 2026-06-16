<div class="aiv-wrap">
    <div class="aiv-between">
        <div>
            <h1 class="aiv-h1">{{ $itemGroup->item_group_title }}</h1>
            <div class="aiv-sub">{{ $itemGroup->brand }} · {{ $itemGroup->category }} · {{ $itemGroup->variant_count }} variants</div>
        </div>
        <a class="aiv-btn" wire:navigate href="{{ route('aiv.batches.results', $itemGroup->batch) }}">← Batch results</a>
    </div>

    <x-aiv::methodology-note />

    <div class="aiv-flex" style="gap:24px; align-items:center; margin-top:.5rem;">
        <div style="text-align:center;">
            <x-aiv::score-gauge :value="(float) ($itemGroup->ai_visibility_score ?? 0)" :display="$itemGroup->ai_visibility_score ?? '—'" />
            <div class="aiv-mut">AI visibility score</div>
        </div>
        <div class="aiv-grid" style="flex:1; min-width:240px;">
            <x-aiv::metric-card label="Surfaced rate" :value="$itemGroup->surfaced_rate !== null ? $itemGroup->surfaced_rate.'%' : '—'" />
            <x-aiv::metric-card label="Avg observed position" :value="$itemGroup->average_position ?? '—'" />
            <x-aiv::metric-card label="Zero-click variants" :value="$itemGroup->zero_click_variant_count" />
        </div>
    </div>

    <h2 class="aiv-h2">Prompt-level results</h2>
    <div class="aiv-card" style="padding:0; overflow:hidden;">
        <table class="aiv-table">
            <thead><tr><th>Prompt</th><th>Platform</th><th>Surfaced</th><th>Match</th><th>Confidence</th><th>Pos</th></tr></thead>
            <tbody>
            @forelse ($results as $r)
                <tr>
                    <td style="max-width:340px;">{{ optional($r->prompt)->prompt_text }}</td>
                    <td class="aiv-mut">{{ $r->platform }}</td>
                    <td>@if ($r->surfaced)<span class="aiv-badge is-ok">yes</span>@else<span class="aiv-badge is-low">no</span>@endif</td>
                    <td class="aiv-mut">{{ str_replace('_', ' ', $r->match_type->value) }}</td>
                    <td>{{ $r->confidence_score }}</td>
                    <td>{{ $r->mention_position ?? $r->citation_position ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="aiv-mut" style="padding:1.25rem;">No results yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($recommendations->isNotEmpty())
        <h2 class="aiv-h2">Recommended actions</h2>
        <div class="aiv-stack">
            @foreach ($recommendations as $rec)
                <div class="aiv-row">
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:500;">{{ str_replace('_', ' ', $rec->action_type->value) }}</div>
                        <div class="aiv-mut">{{ $rec->reason }}</div>
                    </div>
                    <x-aiv::priority-badge :priority="$rec->priority" />
                </div>
            @endforeach
        </div>
    @endif
</div>
