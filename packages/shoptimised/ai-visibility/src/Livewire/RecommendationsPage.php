<?php

namespace Shoptimised\AiVisibility\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;
use Shoptimised\AiVisibility\Enums\ActionType;
use Shoptimised\AiVisibility\Enums\RecommendationStatus;
use Shoptimised\AiVisibility\Models\AuditLog;
use Shoptimised\AiVisibility\Models\FeedActionRecommendation;
use Shoptimised\AiVisibility\Services\RecommendationDetailService;

class RecommendationsPage extends Component
{
    use AuthorizesRequests;
    use UsesPackageLayout;

    #[Url]
    public string $status = '';

    /** Recommendation currently open in the detail modal. */
    public ?int $detailId = null;

    public ?string $appliedMessage = null;

    public function viewDetail(int $id): void
    {
        $recommendation = FeedActionRecommendation::findOrFail($id);
        $this->authorize('view', $recommendation->batch);

        $this->detailId = $id;
        $this->appliedMessage = null;
    }

    public function closeDetail(): void
    {
        $this->reset('detailId', 'appliedMessage');
    }

    /** Push the gap questions for a Q&A recommendation into the feed's Q&A. */
    public function pushQnaToFeed(int $id, RecommendationDetailService $details): void
    {
        $recommendation = FeedActionRecommendation::findOrFail($id);
        $this->authorize('changeStatus', $recommendation);

        $summary = $details->applyQnaToFeed($recommendation);

        if ($summary['questions'] > 0) {
            $recommendation->update(['status' => RecommendationStatus::InProgress->value]);
            AuditLog::record('recommendation.qna_pushed_to_feed', $recommendation, $summary);
            $this->appliedMessage = "Added {$summary['questions']} question(s) to the Q&A of {$summary['products']} product(s) in this item group. Fill in the answers, then re-run a check.";
        } else {
            $this->appliedMessage = 'No competitor-only questions found to add.';
        }
    }

    public function setStatus(int $id, string $status)
    {
        $recommendation = FeedActionRecommendation::findOrFail($id);
        $this->authorize('changeStatus', $recommendation);

        abort_unless(in_array($status, array_map(fn ($s) => $s->value, RecommendationStatus::cases()), true), 422);

        $previous = $recommendation->status instanceof RecommendationStatus
            ? $recommendation->status->value
            : (string) $recommendation->status;

        $recommendation->update(['status' => $status]);

        AuditLog::record('recommendation.status_changed', $recommendation, ['from' => $previous, 'to' => $status]);
    }

    public function render(RecommendationDetailService $details)
    {
        $recommendations = FeedActionRecommendation::query()
            ->with(['itemGroup', 'feed'])
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderByRaw("case priority when 'high' then 1 when 'medium' then 2 else 3 end")
            ->latest()
            ->paginate(20);

        $canManage = auth()->user()?->can('approve_recommendations') ?? false;

        $detail = null;
        if ($this->detailId) {
            $rec = FeedActionRecommendation::with(['itemGroup', 'feed'])->find($this->detailId);
            if ($rec) {
                $isQna = in_array($rec->action_type, [ActionType::AddQna, ActionType::ImproveQna], true);
                $detail = [
                    'rec' => $rec,
                    'is_qna' => $isQna,
                    'questions' => $isQna ? $details->qnaGapQuestions($rec) : collect(),
                ];
            }
        }

        return view('ai-visibility::livewire.recommendations', compact('recommendations', 'canManage', 'detail'))
            ->layout($this->layoutName());
    }
}
