<div class="aiv-wrap">
    <div class="aiv-between">
        <div>
            <h1 class="aiv-h1">AI shopping readiness</h1>
            <div class="aiv-sub">Monitored AI visibility for your product families across AI search platforms.</div>
        </div>
        <div class="aiv-flex" style="gap:8px;">
            <a href="{{ route('aiv.feeds') }}" wire:navigate class="aiv-btn">Import a feed</a>
            <a href="{{ route('aiv.new') }}" wire:navigate class="aiv-btn aiv-btn-primary">New visibility check</a>
        </div>
    </div>

    <div class="aiv-grid" style="margin-top:1.25rem;">
        <x-aiv::metric-card label="Feeds" :value="$stats['feeds']" />
        <x-aiv::metric-card label="Products monitored" :value="number_format($stats['products'])" />
        <x-aiv::metric-card label="Checks run" :value="$stats['checks']" />
        <x-aiv::metric-card label="Avg surfaced rate" :value="$stats['avg_surfaced'].'%'" />
        <x-aiv::metric-card label="Avg visibility score" :value="$stats['avg_score'] ?: '—'" />
        <x-aiv::metric-card label="Products with Q&A" :value="$stats['qna_products'].' / '.number_format($stats['products'])" />
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:16px; margin-top:1.75rem;">
        <div>
            <div class="aiv-between" style="align-items:baseline;">
                <h2 class="aiv-h2" style="margin:0;">Recent checks</h2>
                @if ($batches->isNotEmpty())
                    <a class="aiv-mut" style="text-decoration:none;" wire:navigate href="{{ route('aiv.new') }}">New check →</a>
                @endif
            </div>
            <div class="aiv-stack" style="margin-top:.75rem;">
                @forelse ($batches as $batch)
                    <a class="aiv-row" style="text-decoration:none; color:inherit;" wire:navigate
                       href="{{ $batch->status->value === 'completed' ? route('aiv.batches.results', $batch) : route('aiv.batches.progress', $batch) }}">
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:500;" class="aiv-ellip">{{ $batch->name }}</div>
                            <div class="aiv-mut">{{ optional($batch->feed)->name }} · {{ optional($batch->created_at)->format('d M Y') }}</div>
                        </div>
                        <x-aiv::status-badge :status="$batch->status->value" />
                    </a>
                @empty
                    <div class="aiv-card aiv-mut">No checks yet. <a wire:navigate href="{{ route('aiv.new') }}">Start your first check →</a></div>
                @endforelse
            </div>
        </div>

        <div>
            <div class="aiv-between" style="align-items:baseline;">
                <h2 class="aiv-h2" style="margin:0;">Feeds</h2>
                <a class="aiv-mut" style="text-decoration:none;" wire:navigate href="{{ route('aiv.feeds') }}">All feeds →</a>
            </div>
            <div class="aiv-stack" style="margin-top:.75rem;">
                @forelse ($feeds as $feed)
                    <a class="aiv-row" style="text-decoration:none; color:inherit;" wire:navigate href="{{ route('aiv.feeds.show', $feed) }}">
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:500;" class="aiv-ellip">{{ $feed->name }}</div>
                            <div class="aiv-mut">{{ $feed->products_count }} products · {{ optional($feed->last_imported_at)->format('d M Y') ?? 'not imported' }}</div>
                        </div>
                        <span class="aiv-mut">Reliability →</span>
                    </a>
                @empty
                    <div class="aiv-card aiv-mut">No feeds yet. <a wire:navigate href="{{ route('aiv.feeds') }}">Import a feed →</a></div>
                @endforelse
            </div>
        </div>
    </div>
</div>
