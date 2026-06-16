<?php

namespace Shoptimised\AiVisibility\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Services\FeedReliabilityService;

/**
 * Per-feed data-reliability report: field completeness, item-group/variant
 * coverage, Q&A coverage and AI result variance.
 */
class FeedDetailPage extends Component
{
    use AuthorizesRequests;
    use UsesPackageLayout;

    public int $feedId;

    public function mount(Feed $feed): void
    {
        abort_unless(
            auth()->user()->can('view_reports') && auth()->user()->canAccessRetailer($feed->retailer_id),
            403,
        );

        $this->feedId = $feed->id;
    }

    public function render(FeedReliabilityService $reliability)
    {
        $feed = Feed::findOrFail($this->feedId);
        $report = $reliability->for($feed);

        return view('ai-visibility::livewire.feed-detail', compact('feed', 'report'))
            ->layout($this->layoutName());
    }
}
