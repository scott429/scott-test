<?php

namespace Shoptimised\AiVisibility\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Shoptimised\AiVisibility\Models\AiVisibilityCompetitor;
use Shoptimised\AiVisibility\Models\AiVisibilityResult;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Services\VisibilityResultParser;
use Shoptimised\AiVisibility\Support\TenantContext;

/**
 * Parses one stored result: runs the matcher, confidence scorer and competitor
 * extractor over raw_response and writes the parsed fields + competitor rows
 * back. Idempotent — safe to re-run against the stored response.
 */
class ParseVisibilityResultJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $resultId) {}

    public function handle(VisibilityResultParser $parser, TenantContext $tenant): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $result = AiVisibilityResult::find($this->resultId);
        if (! $result) {
            return;
        }

        $tenant->runAs($result->retailer_id, function () use ($result, $parser) {
            $prompt = $result->prompt;
            $itemGroup = $prompt?->itemGroup;

            $productUrls = $itemGroup
                ? Product::where('feed_id', $itemGroup->feed_id)
                    ->where('item_group_id', $itemGroup->item_group_id)
                    ->pluck('link')
                    ->filter()
                    ->all()
                : [];

            $context = [
                'retailer_domain' => optional($result->retailer)->domain,
                'item_group_title' => $itemGroup?->item_group_title,
                'category' => $itemGroup?->category,
                'product_urls' => $productUrls,
                'prompt_type' => $prompt?->prompt_type?->value,
            ];

            $parsed = $parser->parse($result, $context);

            $result->update([
                'surfaced' => $parsed->surfaced,
                'match_type' => $parsed->matchType->value,
                'confidence_score' => $parsed->confidenceScore,
                'mention_position' => $parsed->mentionPosition,
                'citation_position' => $parsed->citationPosition,
                'surfaced_url' => $parsed->surfacedUrl,
                'surfaced_title' => $parsed->surfacedTitle,
                'response_summary' => $parsed->responseSummary,
                'competitors_surfaced' => array_map(fn ($c) => $c->toArray(), $parsed->competitors),
                'competitor_count' => count($parsed->competitors),
                'qna_theme_gaps' => $parsed->qnaThemeGaps,
                'variant_gaps' => $parsed->variantGaps,
                'related_product_gaps' => $parsed->relatedProductGaps,
                'document_gaps' => $parsed->documentGaps,
            ]);

            // Replace competitor rows so re-parsing stays idempotent.
            AiVisibilityCompetitor::where('result_id', $result->id)->delete();
            foreach ($parsed->competitors as $competitor) {
                AiVisibilityCompetitor::create([
                    'result_id' => $result->id,
                    'retailer_id' => $result->retailer_id,
                    'competitor_domain' => $competitor->domain,
                    'competitor_name' => $competitor->name,
                    'surfaced_url' => $competitor->url,
                    'surfaced_title' => $competitor->title,
                    'mention_position' => $competitor->mentionPosition,
                    'citation_position' => $competitor->citationPosition,
                ]);
            }
        });
    }
}
