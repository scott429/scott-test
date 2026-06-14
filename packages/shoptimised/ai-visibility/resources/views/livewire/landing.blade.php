<div class="aiv-wrap">
    <div class="aiv-between">
        <div>
            <h1 class="aiv-h1">AI shopping readiness</h1>
            <div class="aiv-sub">Monitored AI visibility for your product families across AI search platforms.</div>
        </div>
        <a href="{{ route('aiv.new') }}" wire:navigate class="aiv-btn aiv-btn-primary">New visibility check</a>
    </div>

    <div class="aiv-grid" style="margin-top:1.25rem;">
        <x-aiv::metric-card label="Total checks" :value="$stats['total_batches']" />
        <x-aiv::metric-card label="Completed" :value="$stats['completed']" />
        <x-aiv::metric-card label="Latest avg score"
            :value="$stats['latest_score'] ? round($stats['latest_score'], 1) : '—'" />
    </div>

    <h2 class="aiv-h2">Recent checks</h2>
    <div class="aiv-card" style="padding:0; overflow:hidden;">
        <table class="aiv-table">
            <thead><tr>
                <th>Name</th><th>Feed</th><th>Status</th><th>Item groups</th><th>Run</th><th></th>
            </tr></thead>
            <tbody>
            @forelse ($batches as $batch)
                <tr>
                    <td>{{ $batch->name }}</td>
                    <td class="aiv-mut">{{ optional($batch->feed)->name }}</td>
                    <td><x-aiv::status-badge :status="$batch->status->value" /></td>
                    <td>{{ $batch->total_item_groups }}</td>
                    <td class="aiv-mut">{{ optional($batch->created_at)->format('d M Y') }}</td>
                    <td style="text-align:right;">
                        @if ($batch->status->value === 'completed')
                            <a class="aiv-btn" wire:navigate href="{{ route('aiv.batches.results', $batch) }}">Results</a>
                        @else
                            <a class="aiv-btn" wire:navigate href="{{ route('aiv.batches.progress', $batch) }}">Progress</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="aiv-mut" style="padding:1.25rem;">No checks yet. Start your first visibility check.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
