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
use Shoptimised\AiVisibility\Models\AiVisibilityItemGroup;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\ProductPerformanceDaily;
use Shoptimised\AiVisibility\Support\TenantContext;

/**
 * Entry point of the pipeline. Builds the item-group rows for the batch, then
 * fans out prompt generation. Prompt generation is cheap (no external calls);
 * the run jobs that follow are the expensive, rate-limited part.
 */
class CreateVisibilityBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $batchId) {}

    public function handle(TenantContext $tenant): void
    {
        $batch = AiVisibilityBatch::find($this->batchId);
        if (! $batch || $batch->status === BatchStatus::Cancelled) {
            return;
        }

        $tenant->runAs($batch->retailer_id, function () use ($batch) {
            $batch->update(['status' => BatchStatus::Running, 'started_at' => now()]);

            $itemGroupIds = (array) data_get($batch->selected_filters, 'item_group_ids', []);
            $jobs = [];

            foreach ($itemGroupIds as $itemGroupId) {
                $products = Product::where('feed_id', $batch->feed_id)
                    ->where('item_group_id', $itemGroupId)
                    ->get();

                if ($products->isEmpty()) {
                    continue;
                }

                $externalIds = $products->pluck('product_id_external');
                $performance = ProductPerformanceDaily::where('feed_id', $batch->feed_id)
                    ->whereIn('product_id_external', $externalIds)
                    ->get();

                $clicksByProduct = $performance->groupBy('product_id_external')
                    ->map(fn ($rows) => (int) $rows->sum('clicks'));

                $representative = $products->sortByDesc(
                    fn ($p) => $clicksByProduct[$p->product_id_external] ?? 0
                )->first();

                $zeroClick = $products->filter(
                    fn ($p) => ($clicksByProduct[$p->product_id_external] ?? 0) === 0
                )->count();

                $itemGroup = AiVisibilityItemGroup::create([
                    'batch_id' => $batch->id,
                    'retailer_id' => $batch->retailer_id,
                    'feed_id' => $batch->feed_id,
                    'item_group_id' => $itemGroupId,
                    'item_group_title' => $representative->item_group_title,
                    'representative_product_id' => $representative->id,
                    'representative_product_url' => $representative->link,
                    'brand' => $representative->brand,
                    'category' => $representative->product_type ?? $representative->google_product_category,
                    'variant_count' => $products->count(),
                    'total_impressions' => (int) $performance->sum('impressions'),
                    'total_clicks' => (int) $performance->sum('clicks'),
                    'total_revenue' => (float) $performance->sum('revenue'),
                    'zero_click_variant_count' => $zeroClick,
                ]);

                $jobs[] = new GeneratePromptsForItemGroupJob($itemGroup->id);
            }

            if ($jobs === []) {
                $batch->update(['status' => BatchStatus::Completed, 'completed_at' => now()]);

                return;
            }

            $batchId = $batch->id;
            Bus::batch($jobs)
                ->name("aiv:prompts:{$batchId}")
                ->onQueue(config('ai_visibility.queues.default'))
                ->then(fn () => DispatchPromptRunsJob::dispatch($batchId)
                    ->onQueue(config('ai_visibility.queues.default')))
                ->dispatch();
        });
    }
}
