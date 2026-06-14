<div class="aiv-wrap">
    <h1 class="aiv-h1">Feed action recommendations</h1>
    <x-aiv::methodology-note />

    <div class="aiv-flex" style="margin:1rem 0;">
        <label class="aiv-mut">Status
            <select wire:model.live="status" class="aiv-btn" style="margin-left:8px;">
                <option value="">All</option>
                <option value="suggested">Suggested</option>
                <option value="accepted">Accepted</option>
                <option value="in_progress">In progress</option>
                <option value="completed">Completed</option>
                <option value="rejected">Rejected</option>
            </select>
        </label>
    </div>

    <div class="aiv-stack">
        @forelse ($recommendations as $rec)
            <div class="aiv-row">
                <div style="flex:1; min-width:0;">
                    <div class="aiv-flex">
                        <span style="font-weight:500;">{{ str_replace('_', ' ', $rec->action_type->value) }}</span>
                        <x-aiv::priority-badge :priority="$rec->priority" />
                        <x-aiv::status-badge :status="$rec->status->value" />
                    </div>
                    <div class="aiv-mut">{{ optional($rec->itemGroup)->item_group_title }} — {{ $rec->reason }}</div>
                </div>
                @if ($canManage)
                    <div class="aiv-flex">
                        <button class="aiv-btn" wire:click="setStatus({{ $rec->id }}, 'accepted')">Accept</button>
                        <button class="aiv-btn" wire:click="setStatus({{ $rec->id }}, 'in_progress')">In progress</button>
                        <button class="aiv-btn" wire:click="setStatus({{ $rec->id }}, 'completed')">Complete</button>
                        <button class="aiv-btn" wire:click="setStatus({{ $rec->id }}, 'rejected')">Reject</button>
                    </div>
                @endif
            </div>
        @empty
            <div class="aiv-mut">No recommendations.</div>
        @endforelse
    </div>

    <div style="margin-top:1.25rem;">{{ $recommendations->links() }}</div>
</div>
