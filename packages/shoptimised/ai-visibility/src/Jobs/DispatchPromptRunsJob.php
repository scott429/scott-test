<?php

namespace Shoptimised\AiVisibility\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Shoptimised\AiVisibility\Enums\BatchStatus;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\AiVisibilityPrompt;
use Shoptimised\AiVisibility\Support\TenantContext;

/**
 * Fans out one RunVisibilityPromptJob per (prompt x platform x run) into a job
 * batch. On completion, scoring -> recommendations -> completion run as a chain.
 * Note: the batch "prompt" counters track runs, for a smooth progress bar.
 */
class DispatchPromptRunsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public int $batchId) {}

    public function handle(TenantContext $tenant): void
    {
        $batch = AiVisibilityBatch::find($this->batchId);
        if (! $batch || $batch->status === BatchStatus::Cancelled) {
            return;
        }

        $tenant->runAs($batch->retailer_id, function () use ($batch) {
            $platforms = (array) $batch->platforms;
            $runs = (int) data_get($batch->selected_filters, 'runs_per_prompt',
                config('ai_visibility.limits.max_runs_per_prompt'));

            $promptIds = AiVisibilityPrompt::where('batch_id', $batch->id)->pluck('id');

            $jobs = [];
            foreach ($promptIds as $promptId) {
                foreach ($platforms as $platform) {
                    for ($run = 1; $run <= $runs; $run++) {
                        $jobs[] = new RunVisibilityPromptJob($promptId, $platform, $run);
                    }
                }
            }

            $batch->update(['total_prompts' => count($jobs), 'completed_prompts' => 0, 'failed_prompts' => 0]);

            if ($jobs === []) {
                CompleteVisibilityBatchJob::dispatch($batch->id);

                return;
            }

            $batchId = $batch->id;
            Bus::batch($jobs)
                ->name("aiv:runs:{$batchId}")
                ->allowFailures()
                ->onQueue(config('ai_visibility.queues.ai'))
                ->then(fn () => CalculateItemGroupScoresJob::withChain([
                    new GenerateFeedRecommendationsJob($batchId),
                    new CompleteVisibilityBatchJob($batchId),
                ])->dispatch($batchId))
                ->dispatch();
        });
    }
}
