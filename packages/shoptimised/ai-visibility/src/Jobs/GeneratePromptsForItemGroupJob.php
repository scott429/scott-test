<?php

namespace Shoptimised\AiVisibility\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Shoptimised\AiVisibility\Enums\AttributeType;
use Shoptimised\AiVisibility\Models\AiVisibilityItemGroup;
use Shoptimised\AiVisibility\Models\AiVisibilityPrompt;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\ProductConversationalAttribute;
use Shoptimised\AiVisibility\Services\FaqDiscoveryService;
use Shoptimised\AiVisibility\Services\PromptGenerator;
use Shoptimised\AiVisibility\Support\TenantContext;

class GeneratePromptsForItemGroupJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $itemGroupVisibilityId) {}

    public function handle(PromptGenerator $generator, TenantContext $tenant, FaqDiscoveryService $faqDiscovery): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $itemGroup = AiVisibilityItemGroup::find($this->itemGroupVisibilityId);
        if (! $itemGroup) {
            return;
        }

        $tenant->runAs($itemGroup->retailer_id, function () use ($itemGroup, $generator, $faqDiscovery) {
            $products = Product::where('feed_id', $itemGroup->feed_id)
                ->where('item_group_id', $itemGroup->item_group_id)
                ->get();

            $productIds = $products->pluck('id');

            $variantOptions = ProductConversationalAttribute::whereIn('product_id', $productIds)
                ->where('attribute_type', AttributeType::VariantOption->value)
                ->get()
                ->map(fn ($a) => data_get($a->attribute_value, 'option'))
                ->filter()
                ->unique()
                ->values()
                ->all();

            // Live buyer Q&A from the feed → tested verbatim as qna_led prompts.
            // Supports the importer's {items:[{question,answer}]} shape and the
            // older single {question|value} shape.
            $questions = ProductConversationalAttribute::whereIn('product_id', $productIds)
                ->where('attribute_type', AttributeType::QuestionAndAnswer->value)
                ->where('live_in_feed', true)
                ->get()
                ->flatMap(function ($a) {
                    $items = data_get($a->attribute_value, 'items');

                    if (is_array($items) && $items !== []) {
                        return array_map(fn ($i) => data_get($i, 'question'), $items);
                    }

                    return [data_get($a->attribute_value, 'question') ?? data_get($a->attribute_value, 'value')];
                })
                ->filter()
                ->unique()
                ->values()
                ->all();

            // No buyer Q&A in the feed for this item group: discover the FAQs
            // shoppers most commonly ask (anchored on the GTIN + title) so we can
            // still test Q&A visibility — and later recommend adding the gaps.
            $questionsSource = 'feed_qna';
            if ($questions === []) {
                $discovered = $this->discoverFaqs($itemGroup, $products, $faqDiscovery);
                if ($discovered !== []) {
                    $questions = $discovered;
                    $questionsSource = 'discovered_faq';
                }
            }

            $prices = $products->pluck('price')->filter()->map(fn ($p) => (float) $p);

            $context = [
                'item_group_title' => $itemGroup->item_group_title,
                'brand' => $itemGroup->brand,
                'category' => $itemGroup->category,
                'variant_options' => $variantOptions,
                'questions' => $questions,
                'questions_source' => $questionsSource,
                'price_min' => $prices->min(),
                'price_max' => $prices->max(),
                'currency' => '£',
                'important_attributes' => array_filter([$itemGroup->category]),
            ];

            $options = [
                'limit' => (int) data_get($itemGroup->batch->selected_filters, 'prompts_per_item_group',
                    config('ai_visibility.limits.default_prompts_per_item_group')),
                'country' => data_get($itemGroup->batch->selected_filters, 'country'),
                'language' => data_get($itemGroup->batch->selected_filters, 'language'),
            ];

            foreach ($generator->generate($context, $options) as $spec) {
                AiVisibilityPrompt::create([
                    'batch_id' => $itemGroup->batch_id,
                    'item_group_visibility_id' => $itemGroup->id,
                    'retailer_id' => $itemGroup->retailer_id,
                    'prompt_text' => $spec['prompt_text'],
                    'prompt_type' => $spec['prompt_type'],
                    'source' => $spec['source'] ?? null,
                    'platform' => null,
                    'country' => $spec['country'],
                    'language' => $spec['language'],
                    'status' => 'pending',
                    'run_count' => 0,
                ]);
            }
        });
    }

    /**
     * Best-effort FAQ discovery for a feed with no Q&A. Anchors on a representative
     * GTIN from the item group's products. Any failure yields no questions rather
     * than failing prompt generation.
     *
     * @param  Collection<int,Product>  $products
     * @return string[]
     */
    protected function discoverFaqs(AiVisibilityItemGroup $itemGroup, $products, FaqDiscoveryService $faqDiscovery): array
    {
        try {
            return $faqDiscovery->discover([
                'item_group_title' => $itemGroup->item_group_title,
                'gtin' => $products->pluck('gtin')->filter()->first(),
                'brand' => $itemGroup->brand,
                'category' => $itemGroup->category,
                'country' => data_get($itemGroup->batch->selected_filters, 'country'),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
