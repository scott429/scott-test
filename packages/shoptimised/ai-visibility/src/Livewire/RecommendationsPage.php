<?php

namespace Shoptimised\AiVisibility\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;
use Shoptimised\AiVisibility\Enums\RecommendationStatus;
use Shoptimised\AiVisibility\Models\AuditLog;
use Shoptimised\AiVisibility\Models\FeedActionRecommendation;
use Shoptimised\AiVisibility\Services\RecommendationDetailService;

class RecommendationsPage extends Component
{
    use AuthorizesRequests;
    use ManagesRecommendationDetail;
    use UsesPackageLayout;

    #[Url]
    public string $status = '';

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

        $detail = $this->recommendationDetail($details);

        return view('ai-visibility::livewire.recommendations', compact('recommendations', 'canManage', 'detail'))
            ->layout($this->layoutName());
    }
}
