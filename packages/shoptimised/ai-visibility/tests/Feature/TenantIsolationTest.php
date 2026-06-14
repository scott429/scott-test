<?php

use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\Retailer;
use Shoptimised\AiVisibility\Support\TenantContext;

beforeEach(function () {
    app(TenantContext::class)->forget();
});

it('scopes queries to the active tenant and hides other retailers', function () {
    $retailerA = Retailer::factory()->create();
    $retailerB = Retailer::factory()->create();
    $feedA = Feed::factory()->for($retailerA)->create();
    $feedB = Feed::factory()->for($retailerB)->create();

    Product::factory()->count(3)->for($retailerA)->for($feedA)->create();
    Product::factory()->count(2)->for($retailerB)->for($feedB)->create();

    // With no tenant set (e.g. console / staff) everything is visible.
    expect(Product::count())->toBe(5);

    // Scoped to retailer A, only A's products are visible.
    app(TenantContext::class)->set($retailerA->id);
    expect(Product::count())->toBe(3);

    // A retailer B product cannot be resolved while scoped to A.
    $bProductId = Product::withoutGlobalScopes()->where('retailer_id', $retailerB->id)->value('id');
    expect(Product::find($bProductId))->toBeNull();

    // Clearing the tenant restores full visibility.
    app(TenantContext::class)->forget();
    expect(Product::count())->toBe(5);
});

it('auto-fills retailer_id from the active tenant on create', function () {
    $retailer = Retailer::factory()->create();
    $feed = Feed::factory()->for($retailer)->create();

    app(TenantContext::class)->set($retailer->id);

    $product = Product::create([
        'feed_id' => $feed->id,
        'product_id_external' => 'SKU-AUTO-FILL',
        'title' => 'Auto-filled product',
    ]);

    expect($product->retailer_id)->toBe($retailer->id);
});

it('does not override an explicitly provided retailer_id', function () {
    $tenantA = Retailer::factory()->create();
    $tenantB = Retailer::factory()->create();
    $feedB = Feed::factory()->for($tenantB)->create();

    app(TenantContext::class)->set($tenantA->id);

    $product = Product::create([
        'retailer_id' => $tenantB->id,
        'feed_id' => $feedB->id,
        'product_id_external' => 'SKU-EXPLICIT',
        'title' => 'Explicit retailer product',
    ]);

    expect($product->retailer_id)->toBe($tenantB->id);
});
