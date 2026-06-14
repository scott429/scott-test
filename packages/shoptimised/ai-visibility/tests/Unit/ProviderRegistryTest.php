<?php

use Shoptimised\AiVisibility\Providers\ManualEvidenceProvider;
use Shoptimised\AiVisibility\Providers\OpenAiSearchProvider;
use Shoptimised\AiVisibility\Providers\ProviderRegistry;

function registry(array $overrides = []): ProviderRegistry
{
    return new ProviderRegistry(array_merge([
        'manual' => ['driver' => ManualEvidenceProvider::class, 'enabled' => true],
        'openai' => ['driver' => OpenAiSearchProvider::class, 'enabled' => false],
    ], $overrides));
}

it('resolves the manual provider', function () {
    expect(registry()->resolve('manual'))->toBeInstanceOf(ManualEvidenceProvider::class);
});

it('falls back to manual mode for a disabled platform but keeps its name', function () {
    $provider = registry()->resolve('openai');

    expect($provider)->toBeInstanceOf(ManualEvidenceProvider::class)
        ->and($provider->getName())->toBe('openai');
});

it('resolves the real driver when the platform is enabled', function () {
    $provider = registry(['openai' => ['driver' => OpenAiSearchProvider::class, 'enabled' => true, 'key' => 'sk-test']])
        ->resolve('openai');

    expect($provider)->toBeInstanceOf(OpenAiSearchProvider::class)
        ->and($provider->getName())->toBe('openai');
});

it('reports enabled platforms', function () {
    $registry = registry();
    expect($registry->isEnabled('manual'))->toBeTrue()
        ->and($registry->isEnabled('openai'))->toBeFalse()
        ->and($registry->enabledPlatforms())->toBe(['manual']);
});
