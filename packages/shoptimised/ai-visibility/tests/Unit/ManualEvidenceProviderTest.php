<?php

use Shoptimised\AiVisibility\Providers\ManualEvidenceProvider;

it('returns a pending manual response tagged with its platform', function () {
    $provider = new ManualEvidenceProvider(['name' => 'manual']);
    $response = $provider->runPrompt('Best egg chairs to buy online in the UK');

    expect($response->mode)->toBe('pending')
        ->and($response->platform)->toBe('manual')
        ->and($response->success)->toBeTrue()
        ->and($provider->getName())->toBe('manual');
});

it('can represent another platform in manual mode', function () {
    $provider = new ManualEvidenceProvider(['name' => 'openai']);
    expect($provider->runPrompt('x')->platform)->toBe('openai');
});
