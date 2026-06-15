<div class="aiv-wrap" @if (! $this->batch->status->isTerminal()) wire:poll.2s @endif>
    @php $b = $this->batch; $pct = $b->total_prompts > 0 ? round($b->completed_prompts / $b->total_prompts * 100) : 0; @endphp

    <div class="aiv-between">
        <div>
            <h1 class="aiv-h1">{{ $b->name }}</h1>
            <div class="aiv-sub">{{ optional($b->feed)->name }} · {{ $b->total_item_groups }} item groups</div>
        </div>
        <x-aiv::status-badge :status="$b->status->value" />
    </div>

    <div class="aiv-card" style="margin-top:1.25rem;">
        <div class="aiv-between" style="align-items:baseline;">
            <span class="aiv-mut">Progress</span>
            <span class="aiv-mut">{{ $b->completed_prompts }} / {{ $b->total_prompts }} runs · {{ $pct }}%</span>
        </div>
        <div style="margin-top:8px;"><x-aiv::score-bar :value="$pct" /></div>
        @if ($b->failed_prompts > 0)
            <div class="aiv-mut" style="margin-top:8px; color:var(--aiv-high-fg);">{{ $b->failed_prompts }} runs failed (isolated; the batch continues).</div>
        @endif
    </div>

    @if (! $b->status->isTerminal() && $b->completed_prompts === 0)
        <div class="aiv-method" style="margin-top:1rem;">
            Waiting for a queue worker to process this check. If the bar stays at 0%, start a worker in your terminal:
            <span style="display:inline-block; margin-top:6px; font-family:ui-monospace,monospace; font-size:.78rem; background:var(--aiv-low-bg); color:var(--aiv-low-fg); padding:2px 8px; border-radius:6px;">php artisan queue:work --queue=ai-visibility,parsing,default</span>
        </div>
    @endif

    <div class="aiv-flex" style="margin-top:1.25rem;">
        @if ($b->status->value === 'completed')
            <a class="aiv-btn aiv-btn-primary" wire:navigate href="{{ route('aiv.batches.results', $b) }}">View results</a>
        @elseif (! $b->status->isTerminal())
            <button class="aiv-btn" wire:click="cancel">Cancel check</button>
        @endif
        <a class="aiv-btn" wire:navigate href="{{ route('aiv.landing') }}">Back</a>
    </div>
</div>
