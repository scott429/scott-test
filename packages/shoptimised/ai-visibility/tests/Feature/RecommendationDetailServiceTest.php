<?php

use Shoptimised\AiVisibility\Enums\AttributeType;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\AiVisibilityCompetitor;
use Shoptimised\AiVisibility\Models\AiVisibilityItemGroup;
use Shoptimised\AiVisibility\Models\AiVisibilityPrompt;
use Shoptimised\AiVisibility\Models\AiVisibilityResult;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\FeedActionRecommendation;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\ProductConversationalAttribute;
use Shoptimised\AiVisibility\Models\Retailer;
use Shoptimised\AiVisibility\Services\RecommendationDetailService;
use Shoptimised\AiVisibility\Support\TenantContext;

beforeEach(fn () => app(TenantContext::class)->forget());

function qnaRecommendationFixture(): FeedActionRecommendation
{
    $retailer = Retailer::factory()->create();
    $feed = Feed::factory()->for($retailer)->create();

    Product::factory()->create([
        'retailer_id' => $retailer->id, 'feed_id' => $feed->id,
        'item_group_id' => 'IG-EGG', 'item_group_title' => 'Egg chairs',
    ]);

    $batch = AiVisibilityBatch::create([
        'retailer_id' => $retailer->id, 'feed_id' => $feed->id, 'name' => 'Check',
        'status' => 'completed', 'platforms' => ['gemini'], 'total_item_groups' => 1, 'total_prompts' => 1,
    ]);

    $itemGroup = AiVisibilityItemGroup::create([
        'batch_id' => $batch->id, 'retailer_id' => $retailer->id, 'feed_id' => $feed->id,
        'item_group_id' => 'IG-EGG', 'item_group_title' => 'Egg chairs',
    ]);

    $prompt = AiVisibilityPrompt::create([
        'batch_id' => $batch->id, 'item_group_visibility_id' => $itemGroup->id, 'retailer_id' => $retailer->id,
        'prompt_text' => 'Do egg chairs come with a stand?', 'prompt_type' => 'qna_led',
        'country' => 'GB', 'language' => 'en', 'status' => 'completed', 'run_count' => 1,
    ]);

    // A surfaced miss where a competitor was cited.
    $result = AiVisibilityResult::create([
        'batch_id' => $batch->id, 'prompt_id' => $prompt->id, 'retailer_id' => $retailer->id,
        'platform' => 'gemini', 'surfaced' => false, 'competitor_count' => 1,
    ]);
    AiVisibilityCompetitor::create([
        'result_id' => $result->id, 'retailer_id' => $retailer->id, 'competitor_domain' => 'dunelm.com',
    ]);

    return FeedActionRecommendation::create([
        'retailer_id' => $retailer->id, 'feed_id' => $feed->id, 'batch_id' => $batch->id,
        'item_group_visibility_id' => $itemGroup->id, 'action_type' => 'add_qna',
        'priority' => 'high', 'status' => 'suggested', 'reason' => 'Competitors answered; you did not.',
    ]);
}

it('finds the buyer questions where competitors surfaced but the retailer did not', function () {
    $questions = app(RecommendationDetailService::class)->qnaGapQuestions(qnaRecommendationFixture());

    expect($questions)->toHaveCount(1)
        ->and($questions[0]['question'])->toBe('Do egg chairs come with a stand?')
        ->and($questions[0]['platforms'])->toBe(['gemini'])
        ->and($questions[0]['competitors'])->toBe(['dunelm.com']);
});

it('pushes the gap questions into the item group products as live Q&A', function () {
    $rec = qnaRecommendationFixture();

    $summary = app(RecommendationDetailService::class)->applyQnaToFeed($rec);

    expect($summary)->toBe(['questions' => 1, 'products' => 1]);

    $product = Product::where('item_group_id', 'IG-EGG')->first();
    $attr = ProductConversationalAttribute::where('product_id', $product->id)
        ->where('attribute_type', AttributeType::QuestionAndAnswer->value)
        ->first();

    expect($attr)->not->toBeNull()
        ->and($attr->live_in_feed)->toBeTrue()
        ->and(data_get($attr->attribute_value, 'items.0.question'))->toBe('Do egg chairs come with a stand?')
        ->and(data_get($attr->attribute_value, 'items.0.answer'))->toBe('');
});
