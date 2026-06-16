<?php

namespace Shoptimised\AiVisibility\Livewire;

use Illuminate\Support\Collection;
use Shoptimised\AiVisibility\Enums\ActionType;
use Shoptimised\AiVisibility\Enums\RecommendationStatus;
use Shoptimised\AiVisibility\Models\AuditLog;
use Shoptimised\AiVisibility\Models\FeedActionRecommendation;
use Shoptimised\AiVisibility\Services\RecommendationDetailService;

/**
 * Shared recommendation drill-down modal: opens a recommendation, surfaces the
 * buyer questions where competitors surfaced but the retailer didn't, and pushes
 * those questions into the feed's Q&A. Used by both the recommendations index and
 * the per-batch results page so the interaction stays identical.
 *
 * @property int|null $detailId
 * @property string|null $appliedMessage
 */
trait ManagesRecommendationDetail
{
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

    /**
     * Build the modal payload for the currently open recommendation.
     *
     * @return array{rec: FeedActionRecommendation, is_qna: bool, questions: Collection}|null
     */
    protected function recommendationDetail(RecommendationDetailService $details): ?array
    {
        if (! $this->detailId) {
            return null;
        }

        $rec = FeedActionRecommendation::with(['itemGroup', 'feed'])->find($this->detailId);

        if (! $rec) {
            return null;
        }

        $isQna = in_array($rec->action_type, [ActionType::AddQna, ActionType::ImproveQna], true);

        return [
            'rec' => $rec,
            'is_qna' => $isQna,
            'questions' => $isQna ? $details->qnaGapQuestions($rec) : collect(),
        ];
    }
}
