<div class="aiv-wrap">
    @php $c = $report['completeness']; $g = $report['grouping']; $q = $report['qna']; $v = $report['variance']; @endphp

    <div class="aiv-between">
        <div>
            <h1 class="aiv-h1">{{ $feed->name }}</h1>
            <div class="aiv-sub">Data reliability · {{ $c['total'] }} products · last imported {{ optional($feed->last_imported_at)->format('d M Y H:i') ?? '—' }}</div>
        </div>
        <a class="aiv-btn" wire:navigate href="{{ route('aiv.feeds') }}">← Feeds</a>
    </div>

    <h2 class="aiv-h2">Field completeness</h2>
    <div class="aiv-grid">
        <x-aiv::metric-card label="Products" :value="$c['total']" />
        <x-aiv::metric-card label="Missing brand" :value="$c['missing_brand']" />
        <x-aiv::metric-card label="Missing price" :value="$c['missing_price']" />
        <x-aiv::metric-card label="Missing link" :value="$c['missing_link']" />
        <x-aiv::metric-card label="Missing image" :value="$c['missing_image']" />
        <x-aiv::metric-card label="Missing description" :value="$c['missing_description']" />
    </div>

    <h2 class="aiv-h2">Item group &amp; variant coverage</h2>
    <div class="aiv-grid">
        <x-aiv::metric-card label="Item groups" :value="$g['item_groups']" />
        <x-aiv::metric-card label="Avg variants / group" :value="$g['avg_variants']" />
        <x-aiv::metric-card label="Single-product groups" :value="$g['single_product_groups']" />
    </div>

    <h2 class="aiv-h2">Q&amp;A coverage</h2>
    <div class="aiv-grid">
        <x-aiv::metric-card label="Products with Q&A" :value="$q['products_with_qna'].' / '.$q['total_products']" />
        <x-aiv::metric-card label="Item groups without Q&A" :value="$q['groups_without_qna']" />
        <x-aiv::metric-card label="At the 30 Q&A cap" :value="$q['at_cap']" />
    </div>

    <h2 class="aiv-h2">Result reliability</h2>
    <div class="aiv-card">
        @if ($v['evaluated'] === 0)
            <div class="aiv-mut">No multi-run results yet. Run a check with <strong>runs per prompt &gt; 1</strong> to measure run-to-run consistency.</div>
        @else
            <div class="aiv-flex" style="gap:24px; align-items:baseline;">
                <div><span class="aiv-score">{{ $v['consistency_pct'] }}%</span> <span class="aiv-mut">run-to-run consistency</span></div>
                <div class="aiv-mut">{{ $v['inconsistent'] }} of {{ $v['evaluated'] }} prompt/platform pairs surfaced inconsistently</div>
            </div>
            @if (! empty($v['examples']))
                <div class="aiv-stack" style="margin-top:12px;">
                    @foreach ($v['examples'] as $ex)
                        <div class="aiv-row">
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:500;">{{ $ex['prompt'] }}</div>
                                <div class="aiv-mut">{{ $ex['platform'] }} · surfaced {{ $ex['surfaced'] }} of {{ $ex['runs'] }} runs</div>
                            </div>
                            <span class="aiv-badge is-medium">unstable</span>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
