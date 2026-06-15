<?php

use Illuminate\Support\Facades\Http;
use Shoptimised\AiVisibility\Providers\Exceptions\TransientProviderException;
use Shoptimised\AiVisibility\Providers\GeminiGroundedSearchProvider;

it('returns a pending manual response when no api key is configured', function () {
    $provider = new GeminiGroundedSearchProvider(['name' => 'gemini', 'model' => 'gemini-2.5-flash']);

    $response = $provider->runPrompt('best garden sofas', ['country' => 'GB']);

    expect($response->mode)->toBe('pending')
        ->and($response->success)->toBeTrue()
        ->and($response->citations)->toBe([]);
});

it('maps a grounded gemini response into text and citation domains', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => 'You can buy these at Argos and John Lewis.']]],
                'groundingMetadata' => ['groundingChunks' => [
                    ['web' => ['uri' => 'https://vertexaisearch.cloud.google.com/grounding-api-redirect/abc', 'title' => 'argos.co.uk']],
                    ['web' => ['uri' => 'https://www.johnlewis.com/garden', 'title' => 'A page title with spaces']],
                ]],
            ]],
        ], 200),
    ]);

    $provider = new GeminiGroundedSearchProvider([
        'name' => 'gemini',
        'key' => 'test-key',
        'model' => 'gemini-2.5-flash',
        'supports_citations' => true,
    ]);

    $response = $provider->runPrompt('best garden sofas', ['country' => 'GB']);

    expect($response->success)->toBeTrue()
        ->and($response->mode)->toBe('api')
        ->and($response->text)->toContain('Argos')
        ->and($response->citations)->toHaveCount(2)
        // Domain taken from the chunk title when it looks like a domain...
        ->and($response->citations[0]->domain)->toBe('argos.co.uk')
        // ...otherwise from the URI host.
        ->and($response->citations[1]->domain)->toBe('johnlewis.com');
});

it('returns a failed response on a terminal gemini api error', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response('bad request', 400),
    ]);

    $provider = new GeminiGroundedSearchProvider(['name' => 'gemini', 'key' => 'test-key']);

    $response = $provider->runPrompt('best garden sofas');

    expect($response->success)->toBeFalse()
        ->and($response->error)->toContain('400');
});

it('throws a transient exception on a gemini 429 so the job retries with backoff', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response('quota exceeded', 429),
    ]);

    $provider = new GeminiGroundedSearchProvider(['name' => 'gemini', 'key' => 'test-key']);

    $provider->runPrompt('best garden sofas');
})->throws(TransientProviderException::class);
