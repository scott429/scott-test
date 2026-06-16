<?php

namespace Shoptimised\AiVisibility\Services;

use Illuminate\Support\Collection;
use Shoptimised\AiVisibility\Enums\AttributeType;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\AiVisibilityResult;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\ProductConversationalAttribute;

/**
 * Computes data-reliability metrics for a feed so retailers can trust the
 * imported data before running visibility checks: field completeness,
 * item-group / variant coverage, Q&A coverage and AI result variance.
 */
class FeedReliabilityService
{
    private const QNA_CAP = 30;

    /**
     * @return array{
     *   completeness: array<string,int>,
     *   grouping: array<string,int|float>,
     *   qna: array<string,int>,
     *   variance: array<string,mixed>
     * }
     */
    public function for(Feed $feed): array
    {
        $products = Product::where('feed_id', $feed->id)->get();
        $total = $products->count();
        $productIds = $products->pluck('id');

        return [
            'completeness' => $this->completeness($products, $total),
            'grouping' => $this->grouping($products),
            'qna' => $this->qna($products, $productIds),
            'variance' => $this->variance($feed),
        ];
    }

    /** @return array<string,int> */
    private function completeness($products, int $total): array
    {
        $missing = fn (string $field) => $products->filter(fn ($p) => blank($p->{$field}))->count();

        return [
            'total' => $total,
            'missing_brand' => $missing('brand'),
            'missing_price' => $missing('price'),
            'missing_link' => $missing('link'),
            'missing_image' => $missing('image_link'),
            'missing_description' => $missing('description'),
        ];
    }

    /** @return array<string,int|float> */
    private function grouping($products): array
    {
        $byGroup = $products->groupBy('item_group_id');

        return [
            'item_groups' => $byGroup->count(),
            'single_product_groups' => $byGroup->filter(fn ($g) => $g->count() === 1)->count(),
            'avg_variants' => $byGroup->isNotEmpty() ? round($byGroup->avg(fn ($g) => $g->count()), 1) : 0,
        ];
    }

    /**
     * @param  Collection<int,Product>  $products
     * @return array<string,int>
     */
    private function qna($products, $productIds): array
    {
        $attrs = ProductConversationalAttribute::whereIn('product_id', $productIds)
            ->where('attribute_type', AttributeType::QuestionAndAnswer->value)
            ->where('live_in_feed', true)
            ->get();

        $productsWithQna = $attrs->pluck('product_id')->unique();
        $atCap = $attrs->filter(fn ($a) => count((array) data_get($a->attribute_value, 'items', [])) >= self::QNA_CAP)->count();

        $groupsWithQna = $products->whereIn('id', $productsWithQna)->pluck('item_group_id')->unique();
        $totalGroups = $products->pluck('item_group_id')->unique();

        return [
            'products_with_qna' => $productsWithQna->count(),
            'total_products' => $products->count(),
            'at_cap' => $atCap,
            'groups_without_qna' => max(0, $totalGroups->count() - $groupsWithQna->count()),
        ];
    }

    /**
     * Run-to-run consistency of surfacing per prompt × platform across this
     * feed's checks. A prompt tested more than once that surfaces in some runs
     * but not others is "inconsistent" (low reliability).
     *
     * @return array{evaluated:int,inconsistent:int,consistency_pct:int,examples:array<int,array<string,mixed>>}
     */
    private function variance(Feed $feed): array
    {
        $batchIds = AiVisibilityBatch::where('feed_id', $feed->id)->pluck('id');

        $groups = AiVisibilityResult::query()
            ->join('ai_visibility_prompts as p', 'p.id', '=', 'ai_visibility_results.prompt_id')
            ->whereIn('ai_visibility_results.batch_id', $batchIds)
            ->selectRaw('p.prompt_text, ai_visibility_results.platform, count(*) as runs, sum(ai_visibility_results.surfaced) as surfaced_count')
            ->groupBy('p.prompt_text', 'ai_visibility_results.platform')
            ->having('runs', '>', 1)
            ->get();

        $evaluated = $groups->count();
        $inconsistent = $groups->filter(fn ($g) => (int) $g->surfaced_count > 0 && (int) $g->surfaced_count < (int) $g->runs);

        $examples = $inconsistent->take(5)->map(fn ($g) => [
            'prompt' => $g->prompt_text,
            'platform' => $g->platform,
            'surfaced' => (int) $g->surfaced_count,
            'runs' => (int) $g->runs,
        ])->values()->all();

        return [
            'evaluated' => $evaluated,
            'inconsistent' => $inconsistent->count(),
            'consistency_pct' => $evaluated > 0 ? (int) round(($evaluated - $inconsistent->count()) / $evaluated * 100) : 100,
            'examples' => $examples,
        ];
    }
}
