<?php

namespace Shoptimised\AiVisibility\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Shoptimised\AiVisibility\Models\AiVisibilityItemGroup;
use Shoptimised\AiVisibility\Models\AiVisibilityResult;
use Shoptimised\AiVisibility\Models\FeedActionRecommendation;

class ItemGroupDetailPage extends Component
{
    use AuthorizesRequests;
    use UsesPackageLayout;

    public int $itemGroupId;

    public function mount(AiVisibilityItemGroup $itemGroup): void
    {
        $this->authorize('view', $itemGroup->batch);
        $this->itemGroupId = $itemGroup->id;
    }

    public function render()
    {
        $itemGroup = AiVisibilityItemGroup::with('batch')->findOrFail($this->itemGroupId);

        $promptIds = $itemGroup->prompts()->pluck('id');
        $results = AiVisibilityResult::with('prompt')
            ->whereIn('prompt_id', $promptIds)
            ->orderBy('platform')->get();

        $recommendations = FeedActionRecommendation::where('item_group_visibility_id', $itemGroup->id)->get();

        return view('ai-visibility::livewire.item-group-detail', compact('itemGroup', 'results', 'recommendations'))
            ->layout($this->layoutName());
    }
}
