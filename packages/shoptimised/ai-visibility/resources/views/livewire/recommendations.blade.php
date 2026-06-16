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
                <div style="flex:1; min-width:0; cursor:pointer;" wire:click="viewDetail({{ $rec->id }})">
                    <div class="aiv-flex">
                        <span style="font-weight:500;">{{ str_replace('_', ' ', $rec->action_type->value) }}</span>
                        <x-aiv::priority-badge :priority="$rec->priority" />
                        <x-aiv::status-badge :status="$rec->status->value" />
                    </div>
                    <div class="aiv-mut">{{ optional($rec->itemGroup)->item_group_title }} — {{ $rec->reason }}</div>
                    <div style="font-size:.72rem; color:var(--aiv-blue); margin-top:3px;">View details &amp; evidence →</div>
                </div>
                @if ($canManage)
                    <div class="aiv-flex">
                        <button class="aiv-btn" wire:click="setStatus({{ $rec->id }}, 'accepted')">Accept</button>
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

    @if ($detail)
        @php $rec = $detail['rec']; @endphp
        <div class="aiv-modal-overlay" wire:click.self="closeDetail">
            <div class="aiv-modal">
                <div class="aiv-between">
                    <div>
                        <h2 class="aiv-h2" style="margin:0;">{{ str_replace('_', ' ', $rec->action_type->value) }} — {{ optional($rec->itemGroup)->item_group_title }}</h2>
                        <div class="aiv-mut">{{ $rec->reason }}</div>
                    </div>
                    <button class="aiv-btn" wire:click="closeDetail" aria-label="Close">✕</button>
                </div>

                @if ($appliedMessage)
                    <div class="aiv-method" style="background:var(--aiv-ok-bg); color:var(--aiv-ok-fg); border:none; margin-top:1rem;">{{ $appliedMessage }}</div>
                @endif

                @if ($detail['is_qna'])
                    <h3 style="font-size:.95rem; font-weight:600; margin:1.1rem 0 .5rem;">Questions where competitors surfaced but you didn't</h3>
                    @forelse ($detail['questions'] as $q)
                        <div class="aiv-qrow" style="margin-bottom:8px;">
                            <div style="font-weight:500;">{{ $q['question'] }}</div>
                            <div class="aiv-mut">
                                tested on {{ implode(', ', $q['platforms']) }}
                                @if (! empty($q['competitors'])) · competitors answering: {{ implode(', ', $q['competitors']) }} @endif
                            </div>
                        </div>
                    @empty
                        <div class="aiv-mut">No competitor-only questions found for this item group (the gap may have cleared since this recommendation was generated).</div>
                    @endforelse

                    @if ($canManage && $detail['questions']->isNotEmpty())
                        <div class="aiv-flex" style="margin-top:1rem; justify-content:flex-end; gap:8px;">
                            <button class="aiv-btn" wire:click="closeDetail">Close</button>
                            <button class="aiv-btn aiv-btn-primary" wire:click="pushQnaToFeed({{ $rec->id }})" wire:loading.attr="disabled" wire:target="pushQnaToFeed">
                                <span wire:loading.remove wire:target="pushQnaToFeed">Add {{ $detail['questions']->count() }} to feed Q&A</span>
                                <span wire:loading wire:target="pushQnaToFeed">Adding…</span>
                            </button>
                        </div>
                        <div class="aiv-mut" style="margin-top:6px; text-align:right;">Adds these questions to the item group's products as live Q&A (answers left blank for you to complete).</div>
                    @endif
                @else
                    <div class="aiv-method" style="margin-top:1rem;">
                        This recommendation is based on the prompt results for this item group. Open the
                        <a wire:navigate href="{{ route('aiv.batches.results', $rec->batch_id) }}">batch results</a> for the full evidence.
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
