<?php

use Shoptimised\AiVisibility\Enums\MatchType;
use Shoptimised\AiVisibility\Services\VisibilityMatcher;

function ctx(array $o = []): array
{
    return array_merge([
        'retailer_domain' => 'gardenliving.example',
        'item_group_title' => 'rattan corner sofa sets',
        'category' => 'garden furniture',
        'product_urls' => ['https://gardenliving.example/rattan-corner-sofa-sets/grey'],
    ], $o);
}

function matcher(): VisibilityMatcher
{
    return new VisibilityMatcher;
}

it('detects exact title + retailer url as the strongest match', function () {
    $out = matcher()->match(
        'The best rattan corner sofa sets are sold by gardenliving.example this year.',
        [['url' => 'https://gardenliving.example/rattan-corner-sofa-sets/grey', 'domain' => 'gardenliving.example', 'position' => 1]],
        ctx(),
    );

    expect($out->matchType)->toBe(MatchType::ExactItemGroupAndUrl)
        ->and($out->surfaced)->toBeTrue()
        ->and($out->citationPosition)->toBe(1)
        ->and($out->surfacedUrl)->toBe('https://gardenliving.example/rattan-corner-sofa-sets/grey');
});

it('detects a product url cited without the exact title', function () {
    $out = matcher()->match(
        'Here is a good option for your patio.',
        [['url' => 'https://gardenliving.example/rattan-corner-sofa-sets/grey', 'domain' => 'gardenliving.example', 'position' => 2]],
        ctx(),
    );

    expect($out->matchType)->toBe(MatchType::ProductUrl)->and($out->surfaced)->toBeTrue();
});

it('detects retailer domain only', function () {
    $out = matcher()->match(
        'You could browse a few specialist shops.',
        [['url' => 'https://gardenliving.example/blog/buying-guide', 'domain' => 'gardenliving.example', 'position' => 3]],
        ctx(),
    );

    expect($out->matchType)->toBe(MatchType::RetailerDomain);
});

it('falls to semantic family when the description matches but the retailer is absent', function () {
    $out = matcher()->match(
        'Popular rattan corner sofa garden furniture options include several ranges.',
        [['url' => 'https://argos.co.uk/garden', 'domain' => 'argos.co.uk', 'position' => 1]],
        ctx(),
    );

    expect($out->matchType)->toBe(MatchType::SemanticProductFamily)
        ->and($out->signals->semanticSimilarity)->toBeGreaterThan(0.4);
});

it('falls to category only when overlap is weak', function () {
    $out = matcher()->match(
        'We stock furniture for gardens.',
        [['url' => 'https://argos.co.uk', 'domain' => 'argos.co.uk', 'position' => 1]],
        ctx(['item_group_title' => 'deluxe premium handwoven outdoor lounge collection', 'category' => 'furniture']),
    );

    expect($out->matchType)->toBe(MatchType::CategoryOnly);
});

it('returns none when nothing matches', function () {
    $out = matcher()->match('Hello world.', [], ctx(['item_group_title' => 'egg chairs', 'category' => 'seating']));

    expect($out->matchType)->toBe(MatchType::None)->and($out->surfaced)->toBeFalse();
});
