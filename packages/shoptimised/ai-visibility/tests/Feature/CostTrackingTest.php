<?php

use Illuminate\Support\Facades\Http;
use Shoptimised\AiVisibility\Providers\GeminiGroundedSearchProvider;
use Shoptimised\AiVisibility\Providers\PerplexitySearchProvider;

it('estimates gemini cost from token usage and the configured rate', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'hi']]]]],
            'usageMetadata' => ['totalTokenCount' => 1000],
        ], 200),
    ]);

    $provider = new GeminiGroundedSearchProvider([
        'name' => 'gemini', 'key' => 'k', 'model' => 'gemini-2.5-flash', 'cost_per_million_tokens' => 0.30,
    ]);

    $response = $provider->runPrompt('x');

    expect($response->totalTokens)->toBe(1000)
        ->and($response->costUsd)->toBe(0.0003); // 1000 / 1_000_000 * 0.30
});

it('estimates perplexity cost from token usage and the configured rate', function () {
    Http::fake([
        'api.perplexity.ai/*' => Http::response([
            'choices' => [['message' => ['content' => 'hi']]],
            'usage' => ['total_tokens' => 2000],
        ], 200),
    ]);

    $provider = new PerplexitySearchProvider([
        'name' => 'perplexity', 'key' => 'k', 'model' => 'sonar', 'cost_per_million_tokens' => 1.0,
    ]);

    $response = $provider->runPrompt('x');

    expect($response->totalTokens)->toBe(2000)
        ->and($response->costUsd)->toBe(0.002); // 2000 / 1_000_000 * 1.0
});

it('returns null cost when no usage is present', function () {
    Http::fake([
        'api.perplexity.ai/*' => Http::response([
            'choices' => [['message' => ['content' => 'hi']]],
        ], 200),
    ]);

    $provider = new PerplexitySearchProvider(['name' => 'perplexity', 'key' => 'k', 'cost_per_million_tokens' => 1.0]);

    $response = $provider->runPrompt('x');

    expect($response->totalTokens)->toBeNull()
        ->and($response->costUsd)->toBeNull();
});
