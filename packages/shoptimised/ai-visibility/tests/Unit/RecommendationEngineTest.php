<?php

use Shoptimised\AiVisibility\Enums\ActionType;
use Shoptimised\AiVisibility\Services\RecommendationEngine;

function actions(array $runs, array $ctx = []): array
{
    $context = array_merge(['surfaced_rate' => 30.0, 'zero_click_variant_count' => 1, 'item_group_title' => 'Egg chairs'], $ctx);

    return array_map(
        fn ($a) => $a->actionType,
        (new RecommendationEngine)->forItemGroup($context, $runs),
    );
}

it('recommends adding variant options when variant-led prompts miss', function () {
    $types = actions([['prompt_type' => 'variant_led', 'surfaced' => false, 'competitor_surfaced' => true]]);
    expect($types)->toContain(ActionType::AddVariantOption);
});

it('recommends Q&A when a theme prompt misses but rivals answer', function () {
    $types = actions([['prompt_type' => 'use_case', 'surfaced' => false, 'competitor_surfaced' => true]]);
    expect($types)->toContain(ActionType::AddQna);
});

it('recommends a document link when competitors cite documents', function () {
    $types = actions([['prompt_type' => 'attribute_led', 'surfaced' => true, 'competitor_surfaced' => true, 'document_gap' => true]]);
    expect($types)->toContain(ActionType::AddDocumentLink);
});

it('recommends improving the item group title when discovery prompts miss', function () {
    $types = actions([['prompt_type' => 'generic_discovery', 'surfaced' => false, 'competitor_surfaced' => false]]);
    expect($types)->toContain(ActionType::ImproveItemGroupTitle);
});

it('sets high priority when the surfaced rate is low', function () {
    $list = (new RecommendationEngine)->forItemGroup(
        ['surfaced_rate' => 20.0, 'zero_click_variant_count' => 0, 'item_group_title' => 'Egg chairs'],
        [['prompt_type' => 'generic_discovery', 'surfaced' => false, 'competitor_surfaced' => false]],
    );
    expect($list[0]->priority)->toBe('high');
});
