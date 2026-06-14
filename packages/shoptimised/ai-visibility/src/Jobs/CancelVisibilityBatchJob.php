<?php

namespace Shoptimised\AiVisibility\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Shoptimised\AiVisibility\Enums\BatchStatus;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;

/**
 * Marks the batch cancelled. In-flight run jobs check the batch status at the
 * start of handle() and bail. If a Bus batch id is tracked, it can also be
 * cancelled here.
 */
class CancelVisibilityBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $batchId) {}

    public function handle(): void
    {
        $batch = AiVisibilityBatch::find($this->batchId);
        if (! $batch || $batch->status->isTerminal()) {
            return;
        }

        $batch->update(['status' => BatchStatus::Cancelled, 'completed_at' => now()]);
    }
}
