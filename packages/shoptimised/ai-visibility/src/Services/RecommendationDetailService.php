<?php

namespace Shoptimised\AiVisibility\Services;

use Illuminate\Support\Collection;
use Shoptimised\AiVisibility\Enums\AttributeType;
use Shoptimised\AiVisibility\Models\AiVisibilityResult;
use Shoptimised\AiVisibility\Models\FeedActionRecommendation;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\ProductConversationalAttribute;

/**
 * Turns a recommendation into concrete, evidence-backed detail and applies it.
 * For Q&A recommendations: the buyer questions where competitors surfaced but
 * the retailer did not, and pushing those into the feed's Q&A attribute.
 */
class RecommendationDetailService
{
    /** Theme/question prompt types that drive Q&A recommendations. */
    private const QNA_THEME_TYPES = ['qna_led', 'use_case', 'problem_led', 'attribute_led'];

    /**
     * Buyer questions where a competitor surfaced for this recommendation's item
     * group but the retailer did not — the gap behind an add_qna/improve_qna rec.
     *
     * @return Collection<int,array{question:string,source:?string,platforms:array<int,string>,competitors:array<int,string>}>
     */
    public function qnaGapQuestions(FeedActionRecommendation $recommendation): Collection
    {
        $itemGroup = $recommendation->itemGroup;
        if (! $itemGroup) {
            return collect();
        }

        return AiVisibilityResult::query()
            ->where('batch_id', $itemGroup->batch_id)
            ->where('surfaced', false)
            ->where('competitor_count', '>', 0)
            ->whereHas('prompt', fn ($q) => $q->where('item_group_visibility_id', $itemGroup->id)
                ->whereIn('prompt_type', self::QNA_THEME_TYPES))
            ->with(['prompt', 'competitors'])
            ->get()
            ->groupBy(fn ($r) => (string) optional($r->prompt)->prompt_text)
            ->filter(fn ($rows, $question) => $question !== '')
            ->map(fn ($rows, $question) => [
                'question' => $question,
                'source' => optional($rows->first()->prompt)->source,
                'platforms' => $rows->pluck('platform')->unique()->values()->all(),
                'competitors' => $rows->flatMap(fn ($r) => $r->competitors->pluck('competitor_domain'))
                    ->filter()->unique()->take(5)->values()->all(),
            ])
            ->values();
    }

    /**
     * Push the gap questions into the item group's products as suggested,
     * live Q&A so they are tested on the next check (the retailer fills answers).
     *
     * @return array{questions:int,products:int}
     */
    public function applyQnaToFeed(FeedActionRecommendation $recommendation): array
    {
        $itemGroup = $recommendation->itemGroup;
        $questions = $this->qnaGapQuestions($recommendation)->pluck('question')->all();

        if (! $itemGroup || $questions === []) {
            return ['questions' => 0, 'products' => 0];
        }

        $products = Product::where('feed_id', $itemGroup->feed_id)
            ->where('item_group_id', $itemGroup->item_group_id)
            ->get();

        foreach ($products as $product) {
            $attribute = ProductConversationalAttribute::firstOrNew([
                'product_id' => $product->id,
                'attribute_type' => AttributeType::QuestionAndAnswer->value,
            ]);

            $items = (array) data_get($attribute->attribute_value, 'items', []);
            $existing = collect($items)->pluck('question')->all();

            foreach ($questions as $question) {
                if (! in_array($question, $existing, true)) {
                    $items[] = ['question' => $question, 'answer' => ''];
                    $existing[] = $question;
                }
            }

            $attribute->retailer_id = $itemGroup->retailer_id;
            $attribute->attribute_value = ['items' => $items];
            $attribute->source = $attribute->source ?: 'suggested';
            $attribute->live_in_feed = true;
            $attribute->save();
        }

        return ['questions' => count($questions), 'products' => $products->count()];
    }
}
