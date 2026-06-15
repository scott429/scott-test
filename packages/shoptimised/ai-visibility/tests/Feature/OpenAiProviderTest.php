<?php

use Illuminate\Support\Facades\Http;
use Shoptimised\AiVisibility\Providers\Exceptions\TransientProviderException;
use Shoptimised\AiVisibility\Providers\OpenAiSearchProvider;

it('returns a pending manual response when no api key is configured', function () {
    $provider = new OpenAiSearchProvider(['name' => 'openai', 'model' => 'gpt-4o-search-preview']);

    $response = $provider->runPrompt('best garden sofas', ['country' => 'GB']);

    expect($response->mode)->toBe('pending')
        ->and($response->citations)->toBe([]);
});

it('maps an openai web-search response into text and citation domains', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => 'Try Rattan Direct and John Lewis.',
                    'annotations' => [
                        ['type' => 'url_citation', 'url_citation' => ['url' => 'https://www.rattandirect.co.uk/sofas', 'title' => 'Rattan Direct']],
                        ['type' => 'url_citation', 'url_citation' => ['url' => 'https://johnlewis.com/garden', 'title' => 'John Lewis']],
                    ],
                ],
            ]],
        ], 200),
    ]);

    $provider = new OpenAiSearchProvider(['name' => 'openai', 'key' => 'test-key', 'model' => 'gpt-4o-search-preview']);

    $response = $provider->runPrompt('best rattan corner sofa sets', ['country' => 'GB']);

    expect($response->success)->toBeTrue()
        ->and($response->mode)->toBe('api')
        ->and($response->text)->toContain('Rattan Direct')
        ->and($response->citations)->toHaveCount(2)
        ->and($response->citations[0]->domain)->toBe('rattandirect.co.uk')
        ->and($response->citations[1]->domain)->toBe('johnlewis.com');
});

it('returns a failed response on a terminal openai api error', function () {
    Http::fake([
        'api.openai.com/*' => Http::response('bad request', 400),
    ]);

    $provider = new OpenAiSearchProvider(['name' => 'openai', 'key' => 'test-key']);

    $response = $provider->runPrompt('best garden sofas');

    expect($response->success)->toBeFalse()
        ->and($response->error)->toContain('400');
});

it('throws a transient exception on an openai 5xx so the job retries', function () {
    Http::fake([
        'api.openai.com/*' => Http::response('server error', 503),
    ]);

    $provider = new OpenAiSearchProvider(['name' => 'openai', 'key' => 'test-key']);

    $provider->runPrompt('best garden sofas');
})->throws(TransientProviderException::class);
