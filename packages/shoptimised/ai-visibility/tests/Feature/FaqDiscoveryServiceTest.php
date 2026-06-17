<?php

use Illuminate\Support\Facades\Http;
use Shoptimised\AiVisibility\Providers\ManualEvidenceProvider;
use Shoptimised\AiVisibility\Providers\PerplexitySearchProvider;
use Shoptimised\AiVisibility\Providers\ProviderRegistry;
use Shoptimised\AiVisibility\Services\FaqDiscoveryService;

function discoveryService(array $providers): FaqDiscoveryService
{
    return new FaqDiscoveryService(new ProviderRegistry($providers));
}

$enabledPerplexity = [
    'manual' => ['driver' => ManualEvidenceProvider::class, 'enabled' => true],
    'perplexity' => ['driver' => PerplexitySearchProvider::class, 'enabled' => true, 'key' => 'test-key', 'model' => 'sonar'],
];

it('parses messy provider output into clean question strings', function () {
    $text = "1. Do egg chairs come with a stand?\n- Are hanging egg chairs suitable for outdoors?\n* What weight can an egg chair hold?\nThese are popular.\nDo egg chairs come with a stand?\n";

    $questions = (new FaqDiscoveryService(new ProviderRegistry([])))->parseQuestions($text, 6);

    expect($questions)->toBe([
        'Do egg chairs come with a stand?',
        'Are hanging egg chairs suitable for outdoors?',
        'What weight can an egg chair hold?',
    ]);
});

it('discovers FAQs from the first enabled search provider', function () use ($enabledPerplexity) {
    Http::fake([
        'api.perplexity.ai/*' => Http::response([
            'choices' => [['message' => ['content' => "Are egg chairs weatherproof?\nDo egg chairs come with a stand?"]]],
        ], 200),
    ]);

    $questions = discoveryService($enabledPerplexity)->discover([
        'item_group_title' => 'Egg chairs',
        'gtin' => '5012345678900',
        'brand' => 'Dunelm',
    ]);

    expect($questions)->toBe([
        'Are egg chairs weatherproof?',
        'Do egg chairs come with a stand?',
    ]);

    Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'Egg chairs')
        && str_contains($request['messages'][1]['content'], 'GTIN 5012345678900'));
});

it('returns nothing when only manual evidence is available', function () {
    $questions = discoveryService([
        'manual' => ['driver' => ManualEvidenceProvider::class, 'enabled' => true],
    ])->discover(['item_group_title' => 'Egg chairs']);

    expect($questions)->toBe([]);
});

it('returns nothing when discovery is disabled', function () use ($enabledPerplexity) {
    config()->set('ai_visibility.faq_discovery.enabled', false);

    $questions = discoveryService($enabledPerplexity)->discover(['item_group_title' => 'Egg chairs']);

    expect($questions)->toBe([]);
});

it('returns nothing when the provider call fails', function () use ($enabledPerplexity) {
    Http::fake(['api.perplexity.ai/*' => Http::response('unauthorized', 401)]);

    $questions = discoveryService($enabledPerplexity)->discover(['item_group_title' => 'Egg chairs']);

    expect($questions)->toBe([]);
});
