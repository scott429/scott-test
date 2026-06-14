<?php

namespace Shoptimised\AiVisibility\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Shoptimised\AiVisibility\Enums\BatchStatus;
use Shoptimised\AiVisibility\Enums\EvidenceType;
use Shoptimised\AiVisibility\Enums\MatchType;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\AiVisibilityEvidence;
use Shoptimised\AiVisibility\Models\AiVisibilityPrompt;
use Shoptimised\AiVisibility\Models\AiVisibilityResult;
use Shoptimised\AiVisibility\Providers\ProviderRegistry;
use Shoptimised\AiVisibility\Support\TenantContext;
use Throwable;

/**
 * Runs ONE prompt against ONE platform once. Stores the raw response before any
 * parsing, so a parser bug never loses data and provider failures stay isolated.
 * Parsing/scoring is deferred to ParseVisibilityResultJob (Phase 3).
 */
class RunVisibilityPromptJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $promptId,
        public string $platform,
        public int $runNumber,
    ) {}

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(ProviderRegistry $registry, TenantContext $tenant): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $prompt = AiVisibilityPrompt::find($this->promptId);
        if (! $prompt) {
            return;
        }

        $tenant->runAs($prompt->retailer_id, function () use ($prompt, $registry) {
            $batch = AiVisibilityBatch::find($prompt->batch_id);
            if (! $batch || $batch->status === BatchStatus::Cancelled) {
                return;
            }

            $itemGroup = $prompt->itemGroup;
            $provider = $registry->resolve($this->platform);

            $response = $provider->runPrompt($prompt->prompt_text, [
                'country' => $prompt->country,
                'language' => $prompt->language,
                'retailer_domain' => optional($batch->retailer)->domain,
                'item_group_title' => $itemGroup?->item_group_title,
                'run_number' => $this->runNumber,
            ]);

            $result = AiVisibilityResult::create([
                'batch_id' => $batch->id,
                'prompt_id' => $prompt->id,
                'retailer_id' => $prompt->retailer_id,
                'platform' => $this->platform,
                'model_or_surface' => $response->modelOrSurface,
                'run_number' => $this->runNumber,
                'raw_response' => [
                    'mode' => $response->mode,
                    'success' => $response->success,
                    'text' => $response->text,
                    'citations' => $response->citationsToArray(),
                    'raw' => $response->raw,
                    'error' => $response->error,
                ],
                // Parsed fields are populated in Phase 3.
                'surfaced' => false,
                'match_type' => MatchType::None->value,
                'confidence_score' => 0,
                'tested_at' => now(),
            ]);

            AiVisibilityEvidence::create([
                'result_id' => $result->id,
                'evidence_type' => EvidenceType::RawResponse->value,
                'metadata' => ['mode' => $response->mode, 'platform' => $this->platform],
            ]);

            $prompt->increment('run_count');
            AiVisibilityBatch::whereKey($batch->id)->increment('completed_prompts');

            // Parse on the same batch so scoring only starts once every run is
            // both stored AND parsed. Parsing never re-bills the provider.
            $parseJob = (new ParseVisibilityResultJob($result->id))
                ->onQueue(config('ai_visibility.queues.parsing'));

            if ($this->batch()) {
                $this->batch()->add([$parseJob]);
            } else {
                dispatch($parseJob);
            }
        });
    }

    public function failed(Throwable $e): void
    {
        $batchId = AiVisibilityPrompt::find($this->promptId)?->batch_id;

        if ($batchId !== null) {
            AiVisibilityBatch::whereKey($batchId)->increment('failed_prompts');
        }
    }
}
