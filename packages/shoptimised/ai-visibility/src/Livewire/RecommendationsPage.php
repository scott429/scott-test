<?php

namespace Shoptimised\AiVisibility\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;
use Shoptimised\AiVisibility\Enums\RecommendationStatus;
use Shoptimised\AiVisibility\Models\FeedActionRecommendation;

class RecommendationsPage extends Component
{
    use AuthorizesRequests;
    use UsesPackageLayout;

    #[Url]
    public string $status = '';

    public function setStatus(int $id, string $status)
    {
        $recommendation = FeedActionRecommendation::findOrFail($id);
        $this->authorize('changeStatus', $recommendation);

        abort_unless(in_array($status, array_map(fn ($s) => $s->value, RecommendationStatus::cases()), true), 422);

        $recommendation->update(['status' => $status]);
    }

    public function render()
    {
        $recommendations = FeedActionRecommendation::query()
            ->with(['itemGroup', 'feed'])
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderByRaw("case priority when 'high' then 1 when 'medium' then 2 else 3 end")
            ->latest()
            ->paginate(20);

        $canManage = auth()->user()?->can('approve_recommendations') ?? false;

        return view('ai-visibility::livewire.recommendations', compact('recommendations', 'canManage'))
            ->layout($this->layoutName());
    }
}
