<?php

namespace Shoptimised\AiVisibility\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\AiVisibilityPrompt;
use Shoptimised\AiVisibility\Models\AiVisibilityResult;
use Shoptimised\AiVisibility\Services\ScoringService;
use Shoptimised\AiVisibility\Support\TenantContext;

/**
 * Computes the AI Visibility Score, surfaced rate, average position and top
 * competitors for every item group in the batch, from the parsed results.
 */
class CalculateItemGroupScoresJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $batchId) {}

    public function handle(ScoringService $scoring, TenantContext $tenant): void
    {
        $batch = AiVisibilityBatch::find($this->batchId);
        if (! $batch) {
            return;
        }

        $tenant->runAs($batch->retailer_id, function () use ($batch, $scoring) {
            $platformsTested = max(1, count((array) $batch->platforms));

            foreach ($batch->itemGroups as $itemGroup) {
                $promptIds = AiVisibilityPrompt::where('item_group_visibility_id', $itemGroup->id)->pluck('id');
                $results = AiVisibilityResult::whereIn('prompt_id', $promptIds)->get();

                $runs = $results->map(fn (AiVisibilityResult $r) => [
                    'surfaced' => (bool) $r->surfaced,
                    'position' => $r->mention_position ?? $r->citation_position,
                    'cited' => $r->citation_position !== null,
                    'platform' => $r->platform,
                    'confidence' => (int) $r->confidence_score,
                    'competitor_count' => (int) $r->competitor_count,
                    'competitors' => collect((array) $r->competitors_surfaced)
                        ->pluck('domain')->filter()->all(),
                ])->all();

                $metrics = $scoring->visibilityScore($runs, $platformsTested);

                $itemGroup->update([
                    'ai_visibility_score' => $metrics['score'],
                    'surfaced_rate' => $metrics['surfaced_rate'],
                    'average_position' => $metrics['average_position'],
                    'top_competitors' => $scoring->topCompetitors($runs),
                ]);
            }
        });
    }
}
