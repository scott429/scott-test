<?php

namespace Shoptimised\AiVisibility\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Shoptimised\AiVisibility\Enums\RecommendationStatus;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\AiVisibilityPrompt;
use Shoptimised\AiVisibility\Models\AiVisibilityResult;
use Shoptimised\AiVisibility\Models\FeedActionRecommendation;
use Shoptimised\AiVisibility\Services\RecommendationEngine;
use Shoptimised\AiVisibility\Support\TenantContext;

/**
 * Turns each item group's parsed gaps into feed_action_recommendations. Runs
 * after scoring (so surfaced_rate drives priority). Idempotent per batch.
 */
class GenerateFeedRecommendationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $batchId) {}

    public function handle(RecommendationEngine $engine, TenantContext $tenant): void
    {
        $batch = AiVisibilityBatch::find($this->batchId);
        if (! $batch) {
            return;
        }

        $tenant->runAs($batch->retailer_id, function () use ($batch, $engine) {
            // Idempotent: clear this batch's recommendations before regenerating.
            FeedActionRecommendation::where('batch_id', $batch->id)->delete();

            foreach ($batch->itemGroups as $itemGroup) {
                $prompts = AiVisibilityPrompt::where('item_group_visibility_id', $itemGroup->id)
                    ->get()
                    ->keyBy('id');
                $results = AiVisibilityResult::whereIn('prompt_id', $prompts->keys())->get();

                $runs = $results->map(fn (AiVisibilityResult $r) => [
                    'prompt_type' => optional($prompts->get($r->prompt_id)?->prompt_type)->value,
                    'surfaced' => (bool) $r->surfaced,
                    'competitor_surfaced' => (int) $r->competitor_count > 0,
                    'document_gap' => ! empty($r->document_gaps),
                ])->all();

                $context = [
                    'surfaced_rate' => (float) ($itemGroup->surfaced_rate ?? 0),
                    'zero_click_variant_count' => (int) $itemGroup->zero_click_variant_count,
                    'item_group_title' => $itemGroup->item_group_title,
                ];

                $actions = $engine->forItemGroup($context, $runs);

                foreach ($actions as $action) {
                    FeedActionRecommendation::create([
                        'retailer_id' => $batch->retailer_id,
                        'feed_id' => $batch->feed_id,
                        'batch_id' => $batch->id,
                        'item_group_visibility_id' => $itemGroup->id,
                        'product_id' => $itemGroup->representative_product_id,
                        'action_type' => $action->actionType->value,
                        'priority' => $action->priority,
                        'reason' => $action->reason,
                        'evidence_summary' => $action->evidenceSummary,
                        'status' => RecommendationStatus::Suggested->value,
                    ]);
                }

                $itemGroup->update([
                    'recommended_actions' => array_map(fn ($a) => $a->toArray(), $actions),
                ]);
            }
        });
    }
}
