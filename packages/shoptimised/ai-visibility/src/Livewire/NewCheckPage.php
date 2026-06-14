<?php

namespace Shoptimised\AiVisibility\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Providers\ProviderRegistry;
use Shoptimised\AiVisibility\Services\BatchService;

class NewCheckPage extends Component
{
    use AuthorizesRequests;
    use UsesPackageLayout;

    public ?int $feedId = null;

    public string $search = '';

    /** @var string[] */
    public array $selected = [];

    /** @var string[] */
    public array $platforms = ['manual'];

    public int $runs = 1;

    public int $promptsPerGroup = 10;

    public function mount(): void
    {
        $this->authorize('create', AiVisibilityBatch::class);
        $this->feedId = Feed::query()->value('id');
        $this->promptsPerGroup = (int) config('ai_visibility.limits.default_prompts_per_item_group', 10);
    }

    #[Computed]
    public function feeds()
    {
        return Feed::orderBy('name')->get();
    }

    #[Computed]
    public function itemGroups()
    {
        if (! $this->feedId) {
            return collect();
        }

        return Product::where('feed_id', $this->feedId)
            ->whereNotNull('item_group_id')
            ->when($this->search !== '', fn ($q) => $q->where('item_group_title', 'like', '%'.$this->search.'%'))
            ->selectRaw('item_group_id, max(item_group_title) as item_group_title, max(brand) as brand, count(*) as variant_count')
            ->groupBy('item_group_id')
            ->orderBy('item_group_title')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function availablePlatforms(): array
    {
        return app(ProviderRegistry::class)->platforms();
    }

    #[Computed]
    public function estimatedRuns(): int
    {
        return count($this->selected) * $this->promptsPerGroup * max(1, count($this->platforms)) * max(1, $this->runs);
    }

    public function start(BatchService $batches)
    {
        $this->authorize('create', AiVisibilityBatch::class);

        $this->validate([
            'feedId' => ['required', 'integer'],
            'selected' => ['required', 'array', 'min:1', 'max:'.config('ai_visibility.limits.max_item_groups_per_batch')],
            'platforms' => ['required', 'array', 'min:1'],
            'runs' => ['integer', 'min:1', 'max:'.config('ai_visibility.limits.max_runs_per_prompt')],
        ]);

        $batch = $batches->create([
            'feed_id' => $this->feedId,
            'platforms' => $this->platforms,
            'item_group_ids' => $this->selected,
            'runs_per_prompt' => $this->runs,
            'prompts_per_item_group' => $this->promptsPerGroup,
        ], auth()->user());

        return $this->redirectRoute('aiv.batches.progress', $batch, navigate: true);
    }

    public function render()
    {
        return view('ai-visibility::livewire.new-check')->layout($this->layoutName());
    }
}
