<?php

use Illuminate\Support\Facades\Http;
use Shoptimised\AiVisibility\Enums\AttributeType;
use Shoptimised\AiVisibility\Enums\PromptType;
use Shoptimised\AiVisibility\Jobs\GeneratePromptsForItemGroupJob;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\AiVisibilityItemGroup;
use Shoptimised\AiVisibility\Models\AiVisibilityPrompt;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\ProductConversationalAttribute;
use Shoptimised\AiVisibility\Models\Retailer;
use Shoptimised\AiVisibility\Providers\ManualEvidenceProvider;
use Shoptimised\AiVisibility\Providers\PerplexitySearchProvider;
use Shoptimised\AiVisibility\Providers\ProviderRegistry;
use Shoptimised\AiVisibility\Support\TenantContext;

beforeEach(function () {
    app(TenantContext::class)->forget();

    // Make a real search provider available so discovery has somewhere to ask.
    app()->singleton(ProviderRegistry::class, fn () => new ProviderRegistry([
        'manual' => ['driver' => ManualEvidenceProvider::class, 'enabled' => true],
        'perplexity' => ['driver' => PerplexitySearchProvider::class, 'enabled' => true, 'key' => 'test-key', 'model' => 'sonar'],
    ]));
});

/** @return array{group: AiVisibilityItemGroup} */
function eggChairItemGroup(bool $withFeedQna = false): array
{
    $retailer = Retailer::factory()->create();
    $feed = Feed::factory()->for($retailer)->create();

    $product = Product::factory()->create([
        'retailer_id' => $retailer->id, 'feed_id' => $feed->id,
        'item_group_id' => 'IG-EGG', 'item_group_title' => 'Egg chairs', 'gtin' => '5012345678900',
    ]);

    if ($withFeedQna) {
        ProductConversationalAttribute::create([
            'retailer_id' => $retailer->id, 'product_id' => $product->id,
            'attribute_type' => AttributeType::QuestionAndAnswer->value, 'live_in_feed' => true,
            'attribute_value' => ['items' => [['question' => 'Is the cushion included?', 'answer' => 'Yes.']]],
        ]);
    }

    $batch = AiVisibilityBatch::create([
        'retailer_id' => $retailer->id, 'feed_id' => $feed->id, 'name' => 'Check',
        'status' => 'running', 'platforms' => ['perplexity'], 'total_item_groups' => 1, 'total_prompts' => 0,
        'selected_filters' => ['prompts_per_item_group' => 10, 'country' => 'GB', 'language' => 'en'],
    ]);

    $group = AiVisibilityItemGroup::create([
        'batch_id' => $batch->id, 'retailer_id' => $retailer->id, 'feed_id' => $feed->id,
        'item_group_id' => 'IG-EGG', 'item_group_title' => 'Egg chairs',
    ]);

    return ['group' => $group];
}

it('discovers FAQs and tests them as qna_led prompts when the feed has no Q&A', function () {
    Http::fake([
        'api.perplexity.ai/*' => Http::response([
            'choices' => [['message' => ['content' => "Are egg chairs weatherproof?\nDo egg chairs come with a stand?"]]],
        ], 200),
    ]);

    ['group' => $group] = eggChairItemGroup(withFeedQna: false);

    GeneratePromptsForItemGroupJob::dispatchSync($group->id);

    $qna = AiVisibilityPrompt::where('item_group_visibility_id', $group->id)
        ->where('prompt_type', PromptType::QnaLed->value)->get();

    expect($qna)->toHaveCount(2)
        ->and($qna->pluck('source')->unique()->all())->toBe(['discovered_faq'])
        ->and($qna->pluck('prompt_text')->all())->toContain('Are egg chairs weatherproof?');

    Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'GTIN 5012345678900'));
});

it('does not run discovery when the feed already has Q&A', function () {
    Http::fake(); // any provider call would record here

    ['group' => $group] = eggChairItemGroup(withFeedQna: true);

    GeneratePromptsForItemGroupJob::dispatchSync($group->id);

    $qna = AiVisibilityPrompt::where('item_group_visibility_id', $group->id)
        ->where('prompt_type', PromptType::QnaLed->value)->get();

    expect($qna)->toHaveCount(1)
        ->and($qna->first()->source)->toBe('feed_qna')
        ->and($qna->first()->prompt_text)->toBe('Is the cushion included?');

    Http::assertNothingSent();
});

it('still generates the standard prompts when discovery finds nothing', function () {
    Http::fake([
        'api.perplexity.ai/*' => Http::response(['choices' => [['message' => ['content' => 'No questions found.']]]], 200),
    ]);

    ['group' => $group] = eggChairItemGroup(withFeedQna: false);

    GeneratePromptsForItemGroupJob::dispatchSync($group->id);

    $prompts = AiVisibilityPrompt::where('item_group_visibility_id', $group->id)->get();

    expect($prompts->where('prompt_type', PromptType::QnaLed->value))->toHaveCount(0)
        ->and($prompts->where('prompt_type', PromptType::GenericDiscovery->value))->toHaveCount(1);
});
