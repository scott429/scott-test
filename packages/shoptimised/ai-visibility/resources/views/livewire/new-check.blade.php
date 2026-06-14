<div class="aiv-wrap">
    <h1 class="aiv-h1">New visibility check</h1>
    <div class="aiv-sub">Pick the item group titles to test, choose platforms, and run a controlled prompt check.</div>

    <div class="aiv-card" style="margin-top:1.25rem;">
        <div class="aiv-flex" style="gap:18px;">
            <label>Feed
                <select wire:model.live="feedId" class="aiv-btn" style="margin-left:8px;">
                    @foreach ($this->feeds as $feed)
                        <option value="{{ $feed->id }}">{{ $feed->name }}</option>
                    @endforeach
                </select>
            </label>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search item groups…"
                   class="aiv-btn" style="flex:1; min-width:200px;">
        </div>

        <div class="aiv-flex" style="margin-top:14px; gap:18px;">
            <div>
                <div class="aiv-mut">Platforms</div>
                <div class="aiv-flex">
                    @foreach ($this->availablePlatforms as $platform)
                        <label class="aiv-mut"><input type="checkbox" wire:model.live="platforms" value="{{ $platform }}"> {{ $platform }}</label>
                    @endforeach
                </div>
            </div>
            <label class="aiv-mut">Runs per prompt
                <select wire:model.live="runs" class="aiv-btn" style="margin-left:8px;">
                    @for ($i = 1; $i <= config('ai_visibility.limits.max_runs_per_prompt'); $i++)
                        <option value="{{ $i }}">{{ $i }}</option>
                    @endfor
                </select>
            </label>
        </div>
        @error('selected') <div class="aiv-mut" style="color:var(--aiv-high-fg);margin-top:8px;">{{ $message }}</div> @enderror
        @error('platforms') <div class="aiv-mut" style="color:var(--aiv-high-fg);margin-top:8px;">{{ $message }}</div> @enderror
    </div>

    <h2 class="aiv-h2">Item groups <span class="aiv-mut">({{ count($selected) }} selected)</span></h2>
    <div class="aiv-card" style="padding:0; overflow:hidden;">
        <table class="aiv-table">
            <thead><tr><th style="width:40px;"></th><th>Item group</th><th>Brand</th><th>Variants</th></tr></thead>
            <tbody>
            @forelse ($this->itemGroups as $group)
                <tr>
                    <td><input type="checkbox" wire:model.live="selected" value="{{ $group->item_group_id }}"></td>
                    <td>{{ $group->item_group_title }}</td>
                    <td class="aiv-mut">{{ $group->brand }}</td>
                    <td>{{ $group->variant_count }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="aiv-mut" style="padding:1.25rem;">No item groups match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="aiv-between" style="margin-top:1.25rem; align-items:center;">
        <div class="aiv-mut">
            Estimated runs: <strong>{{ number_format($this->estimatedRuns) }}</strong>
            ({{ count($selected) }} groups × {{ $promptsPerGroup }} prompts × {{ max(1, count($platforms)) }} platforms × {{ $runs }} runs)
        </div>
        <button class="aiv-btn aiv-btn-primary" wire:click="start" wire:loading.attr="disabled"
                @disabled(count($selected) === 0 || count($platforms) === 0)>
            Start check
        </button>
    </div>
</div>
