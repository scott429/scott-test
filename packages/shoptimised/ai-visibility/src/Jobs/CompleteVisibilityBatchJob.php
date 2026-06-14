<?php

namespace Shoptimised\AiVisibility\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Shoptimised\AiVisibility\Enums\BatchStatus;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;

class CompleteVisibilityBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $batchId) {}

    public function handle(): void
    {
        $batch = AiVisibilityBatch::find($this->batchId);
        if (! $batch) {
            return;
        }

        if (in_array($batch->status, [BatchStatus::Cancelled, BatchStatus::Failed], true)) {
            return;
        }

        $batch->update(['status' => BatchStatus::Completed, 'completed_at' => now()]);
    }
}
