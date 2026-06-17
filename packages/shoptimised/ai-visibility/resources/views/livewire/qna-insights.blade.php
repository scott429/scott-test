<div class="aiv-wrap">
    <div class="aiv-between">
        <div>
            <h1 class="aiv-h1">Q&amp;A insights</h1>
            <div class="aiv-sub">How buyer questions surface your products across monitored AI visibility checks.</div>
        </div>
        <a class="aiv-btn" wire:navigate href="{{ route('aiv.recommendations') }}">Recommendations →</a>
    </div>

    <x-aiv::methodology-note />

    <div class="aiv-grid" style="margin-top:12px;">
        <x-aiv::metric-card label="Questions tested" :value="$stats['questions_tested']" />
        <x-aiv::metric-card label="Avg surfaced rate" :value="$stats['avg_surfaced_rate'].'%'" />
        <x-aiv::metric-card label="Products with live Q&A" :value="$stats['qna_products'].' / '.$stats['total_products']" />
        <x-aiv::metric-card label="Q&A actions suggested" :value="$stats['add_qna_recs']" />
    </div>

    <div class="aiv-between" style="margin-top:1.75rem; align-items:baseline;">
        <h2 class="aiv-h2" style="margin:0;">Buyer questions</h2>
        <div class="aiv-flex" style="gap:6px;">
            <button class="aiv-btn" style="font-size:.78rem; padding:4px 10px;" wire:click="$set('sort','runs')" @if($sort==='runs') disabled @endif>Most asked</button>
            <button class="aiv-btn" style="font-size:.78rem; padding:4px 10px;" wire:click="$set('sort','rate')" @if($sort==='rate') disabled @endif>Most surfaced</button>
        </div>
    </div>

    <div class="aiv-card" style="padding:0; overflow:hidden; margin-top:.5rem;">
        <table class="aiv-table">
            <thead><tr><th>Question</th><th>Type</th><th>Times tested</th><th>Surfaced</th><th>Surfaced rate</th></tr></thead>
            <tbody>
            @forelse ($questions as $q)
                <tr>
                    <td style="min-width:0;">
                        <div class="aiv-flex" style="gap:8px; align-items:center;">
                            <span>{{ $q->prompt_text }}</span>
                            <x-aiv::source-badge :source="$q->source" />
                        </div>
                    </td>
                    <td class="aiv-mut">{{ str_replace('_', ' ', $q->prompt_type) }}</td>
                    <td>{{ $q->runs }}</td>
                    <td>{{ $q->surfaced_count }}</td>
                    <td>
                        <div class="aiv-flex" style="gap:8px;">
                            <span style="width:34px;">{{ (int) $q->rate }}%</span>
                            <span style="flex:1; min-width:60px;"><x-aiv::score-bar :value="(int) $q->rate" /></span>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="aiv-mut" style="padding:1.25rem;">
                    No buyer-question results yet. Add live Q&amp;A to your products and run a check with an AI platform
                    so questions can be tested and reported here.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($questions->contains(fn ($q) => $q->source === 'discovered_faq'))
        <div class="aiv-mut" style="margin-top:.5rem; font-size:.8rem;">
            <x-aiv::source-badge source="discovered_faq" /> marks FAQs not in your feed — discovered from the product's GTIN and item group title and tested for you. Add them to your feed Q&A from the recommendations to improve coverage.
        </div>
    @endif

    @if ($topMissed->isNotEmpty())
        <h2 class="aiv-h2">Biggest Q&amp;A gaps</h2>
        <div class="aiv-stack">
            @foreach ($topMissed as $q)
                <div class="aiv-row">
                    <div style="flex:1; min-width:0;">
                        <div class="aiv-flex" style="gap:8px; align-items:center;">
                            <span style="font-weight:500;">{{ $q->prompt_text }}</span>
                            <x-aiv::source-badge :source="$q->source" />
                        </div>
                        <div class="aiv-mut">{{ str_replace('_', ' ', $q->prompt_type) }} · surfaced {{ (int) $q->rate }}% of {{ $q->runs }} tests</div>
                    </div>
                    <span class="aiv-badge is-high">{{ (int) $q->rate }}%</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
