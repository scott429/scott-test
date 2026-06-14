<?php

namespace Shoptimised\AiVisibility\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 6 stub. Will re-run the previous month's prompt set for retailers with
 * recurring monitoring enabled, reusing persisted prompts for trend consistency.
 */
class MonthlyScheduledVisibilityBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        if (! config('ai_visibility.monthly_batch_enabled')) {
            return;
        }

        // Implemented in Phase 6 (recurring monitoring).
    }
}
