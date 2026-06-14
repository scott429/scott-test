<?php

use Shoptimised\AiVisibility\Enums\PromptType;
use Shoptimised\AiVisibility\Services\PromptGenerator;

function generate(array $context, array $options = []): array
{
    // Pass explicit options so the generator never touches the config() helper.
    return (new PromptGenerator)->generate($context, array_merge([
        'limit' => 10,
        'country' => 'GB',
        'language' => 'en',
    ], $options));
}

it('always produces generic discovery and commercial intent prompts', function () {
    $prompts = generate(['item_group_title' => 'Rattan corner sofa sets', 'currency' => '£']);

    $types = array_column($prompts, 'prompt_type');
    expect($types)->toContain(PromptType::GenericDiscovery->value)
        ->and($types)->toContain(PromptType::CommercialIntent->value);
});

it('uses the UK region label for GB and returns no prompts without a title', function () {
    $prompts = generate(['item_group_title' => 'Egg chairs', 'currency' => '£']);
    expect($prompts[0]['prompt_text'])->toContain('UK');

    expect(generate(['item_group_title' => '']))->toBe([]);
});

it('skips variant-led prompts when there are no variant options', function () {
    $without = generate(['item_group_title' => 'Fire pit tables', 'currency' => '£']);
    expect(array_column($without, 'prompt_type'))->not->toContain(PromptType::VariantLed->value);

    $with = generate([
        'item_group_title' => 'Fire pit tables',
        'currency' => '£',
        'variant_options' => ['Round', 'Square'],
    ]);
    expect(array_column($with, 'prompt_type'))->toContain(PromptType::VariantLed->value);
});

it('respects the prompt limit', function () {
    $prompts = generate([
        'item_group_title' => 'Cantilever parasols',
        'currency' => '£',
        'brand' => 'ShadeMaster',
        'price_max' => 149,
        'variant_options' => ['2.5m', '3m', '3.5m', '4m', '5m'],
        'use_cases' => ['patios', 'decking', 'balconies'],
    ], ['limit' => 4]);

    expect($prompts)->toHaveCount(4);
});
