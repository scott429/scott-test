<?php

namespace Shoptimised\AiVisibility\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Shoptimised\AiVisibility\Enums\AttributeType;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\AiVisibilityItemGroup;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\ProductConversationalAttribute;

class LandingPage extends Component
{
    use AuthorizesRequests;
    use UsesPackageLayout;

    public function mount(): void
    {
        $this->authorize('viewAny', AiVisibilityBatch::class);
    }

    public function render()
    {
        $products = Product::count();
        $qnaProducts = ProductConversationalAttribute::where('attribute_type', AttributeType::QuestionAndAnswer->value)
            ->where('live_in_feed', true)
            ->distinct()
            ->count('product_id');

        $stats = [
            'feeds' => Feed::count(),
            'products' => $products,
            'checks' => AiVisibilityBatch::count(),
            'completed' => AiVisibilityBatch::where('status', 'completed')->count(),
            'avg_score' => round((float) AiVisibilityItemGroup::avg('ai_visibility_score'), 1),
            'avg_surfaced' => round((float) AiVisibilityItemGroup::avg('surfaced_rate'), 1),
            'qna_products' => $qnaProducts,
        ];

        $batches = AiVisibilityBatch::with('feed')->latest()->take(6)->get();
        $feeds = Feed::withCount('products')->orderByDesc('last_imported_at')->orderBy('name')->take(5)->get();

        return view('ai-visibility::livewire.landing', compact('stats', 'batches', 'feeds'))
            ->layout($this->layoutName());
    }
}
