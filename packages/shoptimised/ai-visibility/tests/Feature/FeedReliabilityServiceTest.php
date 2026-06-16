<?php

use Shoptimised\AiVisibility\Enums\AttributeType;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\ProductConversationalAttribute;
use Shoptimised\AiVisibility\Models\Retailer;
use Shoptimised\AiVisibility\Services\FeedReliabilityService;
use Shoptimised\AiVisibility\Support\TenantContext;

beforeEach(fn () => app(TenantContext::class)->forget());

function reliabilityFeed(): Feed
{
    $retailer = Retailer::factory()->create();
    $feed = Feed::factory()->for($retailer)->create();

    $make = fn (array $attrs) => Product::factory()->create(array_merge([
        'retailer_id' => $retailer->id,
        'feed_id' => $feed->id,
    ], $attrs));

    // Group A: 3 variants, one missing brand + price.
    $p1 = $make(['item_group_id' => 'IG-A', 'item_group_title' => 'Group A', 'brand' => 'GardenLux', 'price' => 10]);
    $make(['item_group_id' => 'IG-A', 'item_group_title' => 'Group A', 'brand' => null, 'price' => null]);
    $make(['item_group_id' => 'IG-A', 'item_group_title' => 'Group A', 'brand' => 'GardenLux', 'price' => 12]);
    // Group SOLO: single product.
    $make(['item_group_id' => 'IG-SOLO', 'item_group_title' => 'Solo', 'brand' => 'X', 'price' => 5]);

    // One product in Group A has live Q&A.
    ProductConversationalAttribute::create([
        'retailer_id' => $retailer->id,
        'product_id' => $p1->id,
        'attribute_type' => AttributeType::QuestionAndAnswer->value,
        'attribute_value' => ['items' => [['question' => 'Is it weatherproof?', 'answer' => 'Yes']]],
        'source' => 'feed',
        'live_in_feed' => true,
    ]);

    return $feed;
}

it('reports field completeness gaps', function () {
    $report = app(FeedReliabilityService::class)->for(reliabilityFeed());

    expect($report['completeness']['total'])->toBe(4)
        ->and($report['completeness']['missing_brand'])->toBe(1)
        ->and($report['completeness']['missing_price'])->toBe(1);
});

it('reports item group and variant coverage', function () {
    $report = app(FeedReliabilityService::class)->for(reliabilityFeed());

    expect($report['grouping']['item_groups'])->toBe(2)
        ->and($report['grouping']['single_product_groups'])->toBe(1)
        ->and($report['grouping']['avg_variants'])->toBe(2.0);
});

it('reports Q&A coverage including groups without any Q&A', function () {
    $report = app(FeedReliabilityService::class)->for(reliabilityFeed());

    expect($report['qna']['products_with_qna'])->toBe(1)
        ->and($report['qna']['total_products'])->toBe(4)
        ->and($report['qna']['at_cap'])->toBe(0)
        ->and($report['qna']['groups_without_qna'])->toBe(1); // IG-SOLO has none
});

it('reports full consistency when there are no multi-run results', function () {
    $report = app(FeedReliabilityService::class)->for(reliabilityFeed());

    expect($report['variance']['evaluated'])->toBe(0)
        ->and($report['variance']['consistency_pct'])->toBe(100);
});
