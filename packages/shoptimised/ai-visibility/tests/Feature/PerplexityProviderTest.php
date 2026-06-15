<?php

use Illuminate\Support\Facades\Http;
use Shoptimised\AiVisibility\Providers\Exceptions\TransientProviderException;
use Shoptimised\AiVisibility\Providers\PerplexitySearchProvider;

it('returns a pending manual response when no api key is configured', function () {
    $provider = new PerplexitySearchProvider(['name' => 'perplexity', 'model' => 'sonar']);

    $response = $provider->runPrompt('best garden sofas', ['country' => 'GB']);

    expect($response->mode)->toBe('pending')
        ->and($response->citations)->toBe([]);
});

it('maps a perplexity sonar response into text and citation domains', function () {
    Http::fake([
        'api.perplexity.ai/*' => Http::response([
            'choices' => [['message' => ['content' => 'Try Rattan Direct and John Lewis.']]],
            'search_results' => [
                ['title' => 'Rattan Direct', 'url' => 'https://www.rattandirect.co.uk/sofas'],
                ['title' => 'John Lewis', 'url' => 'https://www.johnlewis.com/garden'],
            ],
        ], 200),
    ]);

    $provider = new PerplexitySearchProvider(['name' => 'perplexity', 'key' => 'test-key', 'model' => 'sonar']);

    $response = $provider->runPrompt('best rattan corner sofa sets', ['country' => 'GB']);

    expect($response->success)->toBeTrue()
        ->and($response->mode)->toBe('api')
        ->and($response->text)->toContain('Rattan Direct')
        ->and($response->citations)->toHaveCount(2)
        ->and($response->citations[0]->domain)->toBe('rattandirect.co.uk')
        ->and($response->citations[1]->domain)->toBe('johnlewis.com');
});

it('falls back to a flat citations array when search_results is absent', function () {
    Http::fake([
        'api.perplexity.ai/*' => Http::response([
            'choices' => [['message' => ['content' => 'Options below.']]],
            'citations' => ['https://www.argos.co.uk/garden', 'https://dunelm.com/sofas'],
        ], 200),
    ]);

    $provider = new PerplexitySearchProvider(['name' => 'perplexity', 'key' => 'test-key']);

    $response = $provider->runPrompt('best garden sofas');

    expect($response->citations)->toHaveCount(2)
        ->and($response->citations[0]->domain)->toBe('argos.co.uk')
        ->and($response->citations[1]->domain)->toBe('dunelm.com');
});

it('returns a failed response on a perplexity api error', function () {
    Http::fake([
        'api.perplexity.ai/*' => Http::response('unauthorized', 401),
    ]);

    $provider = new PerplexitySearchProvider(['name' => 'perplexity', 'key' => 'bad-key']);

    $response = $provider->runPrompt('best garden sofas');

    expect($response->success)->toBeFalse()
        ->and($response->error)->toContain('401');
});

it('throws a transient exception on a perplexity 429 so the job retries', function () {
    Http::fake([
        'api.perplexity.ai/*' => Http::response('rate limited', 429),
    ]);

    $provider = new PerplexitySearchProvider(['name' => 'perplexity', 'key' => 'test-key']);

    $provider->runPrompt('best garden sofas');
})->throws(TransientProviderException::class);
