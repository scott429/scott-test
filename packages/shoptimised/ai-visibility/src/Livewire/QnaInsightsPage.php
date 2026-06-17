<?php

namespace Shoptimised\AiVisibility\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\AiVisibilityResult;
use Shoptimised\AiVisibility\Models\FeedActionRecommendation;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\ProductConversationalAttribute;

/**
 * Aggregate Q&A / buyer-question insights across all of the retailer's checks:
 * which questions are tested most, which surface the retailer most, how much of
 * the catalogue has live Q&A content, and where AI suggests adding Q&A.
 */
class QnaInsightsPage extends Component
{
    use AuthorizesRequests;
    use UsesPackageLayout;

    /** Buyer-question prompt types this report aggregates. */
    private const QUESTION_TYPES = ['qna_led', 'use_case', 'problem_led', 'attribute_led', 'comparison'];

    public string $sort = 'runs';

    public function mount(): void
    {
        $this->authorize('viewAny', AiVisibilityBatch::class);
    }

    public function render()
    {
        $orderColumn = $this->sort === 'rate' ? 'rate' : 'runs';

        $questions = AiVisibilityResult::query()
            ->join('ai_visibility_prompts as p', 'p.id', '=', 'ai_visibility_results.prompt_id')
            ->whereIn('p.prompt_type', self::QUESTION_TYPES)
            // Alias the sum as surfaced_count (not "surfaced") to avoid colliding
            // with the model's boolean cast on the surfaced column.
            ->selectRaw('p.prompt_text, p.prompt_type, p.source, count(*) as runs, '
                .'sum(ai_visibility_results.surfaced) as surfaced_count, '
                .'round(avg(ai_visibility_results.surfaced) * 100) as rate')
            ->groupBy('p.prompt_text', 'p.prompt_type', 'p.source')
            ->orderByDesc($orderColumn)
            ->orderByDesc('runs')
            ->limit(50)
            ->get();

        $totalRuns = (int) $questions->sum('runs');
        $surfacedRuns = (int) $questions->sum('surfaced_count');

        $liveQnaProducts = ProductConversationalAttribute::query()
            ->where('attribute_type', 'question_and_answer')
            ->where('live_in_feed', true)
            ->distinct()
            ->count('product_id');

        $stats = [
            'questions_tested' => $questions->count(),
            'avg_surfaced_rate' => $totalRuns > 0 ? round($surfacedRuns / $totalRuns * 100) : 0,
            'qna_products' => $liveQnaProducts,
            'total_products' => Product::count(),
            'add_qna_recs' => FeedActionRecommendation::whereIn('action_type', ['add_qna', 'improve_qna'])->count(),
        ];

        $topMissed = $questions->where('runs', '>', 0)->sortBy('rate')->take(5)->values();

        return view('ai-visibility::livewire.qna-insights', compact('questions', 'stats', 'topMissed'))
            ->layout($this->layoutName());
    }
}
