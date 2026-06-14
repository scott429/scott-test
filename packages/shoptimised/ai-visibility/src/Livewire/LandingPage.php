<?php

namespace Shoptimised\AiVisibility\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;

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
        $batches = AiVisibilityBatch::latest()->take(10)->get();

        $stats = [
            'total_batches' => AiVisibilityBatch::count(),
            'completed' => AiVisibilityBatch::where('status', 'completed')->count(),
            'latest_score' => $batches->firstWhere('status', 'completed')
                ?->itemGroups()->avg('ai_visibility_score'),
        ];

        return view('ai-visibility::livewire.landing', compact('batches', 'stats'))
            ->layout($this->layoutName());
    }
}
