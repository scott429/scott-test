<?php

namespace Shoptimised\AiVisibility\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Shoptimised\AiVisibility\Jobs\CancelVisibilityBatchJob;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;

class BatchProgressPage extends Component
{
    use AuthorizesRequests;
    use UsesPackageLayout;

    public int $batchId;

    public function mount(AiVisibilityBatch $batch): void
    {
        $this->authorize('view', $batch);
        $this->batchId = $batch->id;
    }

    #[Computed]
    public function batch()
    {
        return AiVisibilityBatch::findOrFail($this->batchId);
    }

    public function cancel()
    {
        $batch = $this->batch();
        $this->authorize('cancel', $batch);
        CancelVisibilityBatchJob::dispatch($batch->id)->onQueue(config('ai_visibility.queues.default'));
    }

    public function render()
    {
        return view('ai-visibility::livewire.batch-progress')->layout($this->layoutName());
    }
}
