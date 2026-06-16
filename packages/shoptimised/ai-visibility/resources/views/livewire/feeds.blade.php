<div class="aiv-wrap">
    <h1 class="aiv-h1">Product feeds</h1>
    <div class="aiv-sub">Import a product feed from a URL, then check its data reliability before running visibility checks.</div>

    <div class="aiv-card" style="margin-top:1.25rem;">
        <div style="font-weight:600; margin-bottom:12px;">Import a feed</div>

        @if ($importedMessage)
            <div class="aiv-method" style="background:var(--aiv-ok-bg); color:var(--aiv-ok-fg); border:none;">{{ $importedMessage }}</div>
        @endif

        <div class="aiv-flex" style="gap:12px; align-items:flex-end;">
            <label style="flex:2; min-width:240px;">
                <div class="aiv-mut">Feed URL (Google Shopping XML or TSV)</div>
                <input type="url" wire:model="feedUrl" placeholder="https://your-store/products.xml" class="aiv-btn" style="width:100%; margin-top:4px;">
            </label>
            <label style="flex:1; min-width:140px;">
                <div class="aiv-mut">Feed name (optional)</div>
                <input type="text" wire:model="feedName" placeholder="Outdoor Living feed" class="aiv-btn" style="width:100%; margin-top:4px;">
            </label>
            @if ($this->retailers->count() > 1)
                <label style="min-width:160px;">
                    <div class="aiv-mut">Retailer</div>
                    <select wire:model="retailerId" class="aiv-btn" style="margin-top:4px;">
                        @foreach ($this->retailers as $retailer)
                            <option value="{{ $retailer->id }}">{{ $retailer->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <button class="aiv-btn aiv-btn-primary" wire:click="import" wire:loading.attr="disabled" wire:target="import">
                <span wire:loading.remove wire:target="import">Import feed</span>
                <span wire:loading wire:target="import">Importing…</span>
            </button>
        </div>
        @error('feedUrl') <div class="aiv-mut" style="color:var(--aiv-high-fg); margin-top:8px;">{{ $message }}</div> @enderror
        <div class="aiv-mut" style="margin-top:8px;">Large feeds can also be imported from the CLI: <span style="font-family:ui-monospace,monospace;">php artisan ai-visibility:import-feed</span></div>
    </div>

    <h2 class="aiv-h2">Feeds</h2>
    <div class="aiv-card" style="padding:0; overflow:hidden;">
        <table class="aiv-table">
            <thead><tr><th>Feed</th><th>Products</th><th>Country</th><th>Last imported</th><th></th></tr></thead>
            <tbody>
            @forelse ($this->feeds as $feed)
                <tr>
                    <td>{{ $feed->name }}</td>
                    <td>{{ $feed->products_count }}</td>
                    <td class="aiv-mut">{{ $feed->country ?: '—' }}</td>
                    <td class="aiv-mut">{{ optional($feed->last_imported_at)->format('d M Y H:i') ?? '—' }}</td>
                    <td style="text-align:right;"><a class="aiv-btn" wire:navigate href="{{ route('aiv.feeds.show', $feed) }}">Reliability →</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="aiv-mut" style="padding:1.25rem;">No feeds yet. Import one above to get started.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
