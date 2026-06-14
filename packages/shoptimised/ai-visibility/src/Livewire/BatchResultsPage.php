<?php

namespace Shoptimised\AiVisibility\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\AiVisibilityCompetitor;
use Shoptimised\AiVisibility\Models\AiVisibilityResult;
use Shoptimised\AiVisibility\Models\FeedActionRecommendation;
use Shoptimised\AiVisibility\Services\ScoringService;

class BatchResultsPage extends Component
{
    use AuthorizesRequests;
    use UsesPackageLayout;

    public int $batchId;

    public function mount(AiVisibilityBatch $batch): void
    {
        $this->authorize('view', $batch);
        $this->batchId = $batch->id;
    }

    public function render(ScoringService $scoring)
    {
        $batch = AiVisibilityBatch::with(['itemGroups', 'feed'])->findOrFail($this->batchId);
        $itemGroups = $batch->itemGroups->sortByDesc('ai_visibility_score')->values();

        $results = AiVisibilityResult::where('batch_id', $batch->id)->get();
        $runs = $results->map(fn ($r) => [
            'surfaced' => (bool) $r->surfaced,
            'cited' => $r->citation_position !== null,
            'platform' => $r->platform,
        ])->all();

        $exec = [
            'score' => round((float) $itemGroups->avg('ai_visibility_score'), 1),
            'surfaced_rate' => round((float) $itemGroups->avg('surfaced_rate'), 1),
            'avg_position' => round((float) $itemGroups->whereNotNull('average_position')->avg('average_position'), 1),
            'prompts_tested' => $results->count(),
            'platforms_tested' => count((array) $batch->platforms),
            'competitors' => AiVisibilityCompetitor::where('retailer_id', $batch->retailer_id)
                ->whereIn('result_id', $results->pluck('id'))->distinct('competitor_domain')->count('competitor_domain'),
            'recommendations' => FeedActionRecommendation::where('batch_id', $batch->id)->count(),
        ];

        $platforms = $scoring->platformVisibility($runs);
        arsort($platforms);

        $topCompetitors = AiVisibilityCompetitor::query()
            ->whereIn('result_id', $results->pluck('id'))
            ->selectRaw('competitor_domain, count(*) as mentions')
            ->groupBy('competitor_domain')->orderByDesc('mentions')->limit(6)->get();

        $recommendations = FeedActionRecommendation::where('batch_id', $batch->id)
            ->orderByRaw("case priority when 'high' then 1 when 'medium' then 2 else 3 end")
            ->get();

        return view('ai-visibility::livewire.batch-results', compact(
            'batch', 'itemGroups', 'exec', 'platforms', 'topCompetitors', 'recommendations'
        ))->layout($this->layoutName());
    }
}
