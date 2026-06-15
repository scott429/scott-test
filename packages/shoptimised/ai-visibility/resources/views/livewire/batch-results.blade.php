<div class="aiv-wrap">
    <div class="aiv-between">
        <div>
            <h1 class="aiv-h1">{{ $batch->name }}</h1>
            <div class="aiv-sub">{{ optional($batch->feed)->name }} · {{ $batch->total_item_groups }} item groups · {{ optional($batch->completed_at)->format('d M Y') }}</div>
        </div>
        <a class="aiv-btn" wire:navigate href="{{ route('aiv.recommendations') }}">Recommendations →</a>
    </div>

    <x-aiv::methodology-note />

    <div class="aiv-card">
        <div class="aiv-between" style="align-items:baseline;">
            <span class="aiv-mut">Overall AI visibility score</span>
            <span class="aiv-mut">weighted across {{ $itemGroups->count() }} item groups</span>
        </div>
        <div class="aiv-flex" style="align-items:baseline; gap:6px; margin:6px 0 10px;">
            <span class="aiv-score">{{ $exec['score'] ?: '—' }}</span><span class="aiv-mut">/ 100</span>
        </div>
        <x-aiv::score-bar :value="$exec['score']" />
    </div>

    <div class="aiv-grid" style="margin-top:12px;">
        <x-aiv::metric-card label="Retailer surfaced rate" :value="$exec['surfaced_rate'].'%'" />
        <x-aiv::metric-card label="Avg observed position" :value="$exec['avg_position'] ?: '—'" />
        <x-aiv::metric-card label="Prompts tested" :value="$exec['prompts_tested']" />
        <x-aiv::metric-card label="Platforms tested" :value="$exec['platforms_tested']" />
        <x-aiv::metric-card label="Competitors surfaced" :value="$exec['competitors']" />
        <x-aiv::metric-card label="Recommendations" :value="$exec['recommendations']" />
        @if (($exec['spend'] ?? 0) > 0)
            <x-aiv::metric-card label="Estimated spend" :value="'$'.number_format($exec['spend'], 2)" />
        @endif
    </div>

    <h2 class="aiv-h2">Item group leaderboard</h2>
    <div class="aiv-card" style="padding:0; overflow:hidden;">
        <table class="aiv-table">
            <thead><tr><th>Item group</th><th>Score</th><th>Surfaced</th><th>Avg pos</th><th>Top competitor</th><th></th></tr></thead>
            <tbody>
            @foreach ($itemGroups as $group)
                <tr>
                    <td>{{ $group->item_group_title }}</td>
                    <td style="color:var(--aiv-primary); font-weight:600;">{{ $group->ai_visibility_score ?? '—' }}</td>
                    <td>{{ $group->surfaced_rate !== null ? $group->surfaced_rate.'%' : '—' }}</td>
                    <td>{{ $group->average_position ?? '—' }}</td>
                    <td class="aiv-mut">{{ data_get($group->top_competitors, '0.domain', '—') }}</td>
                    <td style="text-align:right;"><a class="aiv-btn" wire:navigate href="{{ route('aiv.groups.show', $group) }}">Detail</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px; margin-top:1.25rem;">
        <div class="aiv-card">
            <div style="font-weight:600; margin-bottom:10px;">Platform breakdown</div>
            @forelse ($platforms as $name => $p)
                <div style="margin-bottom:10px;">
                    <div class="aiv-between" style="font-size:.85rem;"><span>{{ $name }}</span><span>{{ round($p['score']) }}</span></div>
                    <x-aiv::score-bar :value="$p['score']" />
                </div>
            @empty
                <div class="aiv-mut">No platform data yet.</div>
            @endforelse
        </div>
        <div class="aiv-card">
            <div style="font-weight:600; margin-bottom:10px;">Top competitors surfaced</div>
            @forelse ($topCompetitors as $c)
                <div class="aiv-between" style="font-size:.85rem; padding:5px 0; border-bottom:1px solid var(--aiv-border);">
                    <span>{{ $c->competitor_domain }}</span><span class="aiv-mut">{{ $c->mentions }} mentions</span>
                </div>
            @empty
                <div class="aiv-mut">No competitors surfaced.</div>
            @endforelse
        </div>
    </div>

    <h2 class="aiv-h2">Recommended feed actions</h2>
    <div class="aiv-stack">
        @forelse ($recommendations as $rec)
            <div class="aiv-row">
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:500;">{{ str_replace('_', ' ', $rec->action_type->value) }} — {{ optional($rec->itemGroup)->item_group_title }}</div>
                    <div class="aiv-mut">{{ $rec->reason }}</div>
                </div>
                <x-aiv::priority-badge :priority="$rec->priority" />
            </div>
        @empty
            <div class="aiv-mut">No recommendations generated.</div>
        @endforelse
    </div>
</div>
