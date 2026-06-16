<?php

use Shoptimised\AiVisibility\Enums\AttributeType;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\ProductConversationalAttribute;
use Shoptimised\AiVisibility\Models\Retailer;
use Shoptimised\AiVisibility\Services\FeedImporter;
use Shoptimised\AiVisibility\Support\TenantContext;

function sampleGoogleFeedXml(): string
{
    return <<<'XML'
<?xml version="1.0"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
  <item>
    <g:id>SKU1</g:id>
    <g:item_group_id>IG1</g:item_group_id>
    <g:title>Rattan corner sofa set - Grey</g:title>
    <g:brand>GardenLux</g:brand>
    <g:product_type>Garden furniture &gt; Sofas</g:product_type>
    <g:price>699.00 GBP</g:price>
    <g:link>https://gardenliving.example/rattan-grey</g:link>
    <g:color>Grey</g:color>
    <g:question_and_answer>Are rattan sofas weatherproof? Yes fully weatherproof., Do they include cushions? Yes water-resistant covers included.</g:question_and_answer>
  </item>
  <item>
    <g:id>SKU2</g:id>
    <g:item_group_id>IG1</g:item_group_id>
    <g:title>Rattan corner sofa set - Brown</g:title>
    <g:brand>GardenLux</g:brand>
    <g:price>729.00 GBP</g:price>
    <g:link>https://gardenliving.example/rattan-brown</g:link>
    <g:color>Brown</g:color>
  </item>
  <item>
    <g:id>SKU3</g:id>
    <g:title>Cantilever parasol 3m</g:title>
    <g:brand>ShadeMaster</g:brand>
    <g:price>149.00 GBP</g:price>
    <g:link>https://gardenliving.example/parasol-3m</g:link>
  </item>
</channel>
</rss>
XML;
}

beforeEach(function () {
    app(TenantContext::class)->forget();
});

it('imports a google shopping xml feed into feeds, products and attributes', function () {
    $retailer = Retailer::factory()->create();

    $summary = app(FeedImporter::class)->import($retailer->id, sampleGoogleFeedXml(), [
        'name' => 'Outdoor feed',
        'country' => 'GB',
        'currency' => 'GBP',
        'source_url' => 'https://gardenliving.example/feed.xml',
    ]);

    expect($summary['products'])->toBe(3)
        ->and($summary['item_groups'])->toBe(2)
        ->and($summary['variant_options'])->toBe(2);

    $feed = Feed::find($summary['feed_id']);
    expect($feed->name)->toBe('Outdoor feed')
        ->and($feed->retailer_id)->toBe($retailer->id)
        ->and($feed->last_imported_at)->not->toBeNull();

    expect(Product::where('feed_id', $feed->id)->count())->toBe(3);

    // Variants in the same item group share a derived group title (common prefix).
    $grey = Product::where('product_id_external', 'SKU1')->first();
    expect($grey->item_group_id)->toBe('IG1')
        ->and($grey->item_group_title)->toBe('Rattan corner sofa set')
        ->and((float) $grey->price)->toBe(699.00);

    // A product with no item_group_id becomes its own single-variant group.
    $parasol = Product::where('product_id_external', 'SKU3')->first();
    expect($parasol->item_group_id)->toBe('SKU3');

    // Variant option captured from g:color for the prompt generator.
    $variant = ProductConversationalAttribute::where('product_id', $grey->id)
        ->where('attribute_type', AttributeType::VariantOption->value)
        ->first();
    expect(data_get($variant->attribute_value, 'option'))->toBe('Grey')
        ->and($variant->live_in_feed)->toBeTrue();
});

it('imports buyer Q&A from the Question_And_Answer field as live conversational attributes', function () {
    $retailer = Retailer::factory()->create();

    $summary = app(FeedImporter::class)->import($retailer->id, sampleGoogleFeedXml(), ['name' => 'Outdoor feed']);

    expect($summary['qna_entries'])->toBe(2);

    $grey = Product::where('product_id_external', 'SKU1')->first();
    $qna = ProductConversationalAttribute::where('product_id', $grey->id)
        ->where('attribute_type', AttributeType::QuestionAndAnswer->value)
        ->first();

    expect($qna)->not->toBeNull()
        ->and($qna->live_in_feed)->toBeTrue();

    $items = data_get($qna->attribute_value, 'items');
    expect($items)->toHaveCount(2)
        ->and(data_get($items, '0.question'))->toBe('Are rattan sofas weatherproof?')
        ->and(data_get($items, '0.answer'))->toBe('Yes fully weatherproof.');
});

it('caps Q&A at 30 entries per product (Google maximum)', function () {
    $retailer = Retailer::factory()->create();

    $questions = implode(', ', array_map(fn ($i) => "Question number {$i}?", range(1, 40)));
    $xml = <<<XML
    <?xml version="1.0"?>
    <rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
    <channel>
      <item>
        <g:id>SKU-QNA</g:id>
        <g:title>Q and A heavy product</g:title>
        <g:question_and_answer>{$questions}</g:question_and_answer>
      </item>
    </channel>
    </rss>
    XML;

    $summary = app(FeedImporter::class)->import($retailer->id, $xml, ['name' => 'QA cap feed']);

    expect($summary['qna_entries'])->toBe(30);

    $product = Product::where('product_id_external', 'SKU-QNA')->first();
    $qna = ProductConversationalAttribute::where('product_id', $product->id)
        ->where('attribute_type', AttributeType::QuestionAndAnswer->value)
        ->first();

    expect(data_get($qna->attribute_value, 'items'))->toHaveCount(30);
});

it('is idempotent when the same feed is imported twice', function () {
    $retailer = Retailer::factory()->create();

    app(FeedImporter::class)->import($retailer->id, sampleGoogleFeedXml(), ['name' => 'Outdoor feed']);
    $summary = app(FeedImporter::class)->import($retailer->id, sampleGoogleFeedXml(), ['name' => 'Outdoor feed']);

    expect(Feed::where('retailer_id', $retailer->id)->count())->toBe(1)
        ->and(Product::count())->toBe(3)
        ->and($summary['products'])->toBe(3);
});
