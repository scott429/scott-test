<?php

use Shoptimised\AiVisibility\Enums\MatchType;
use Shoptimised\AiVisibility\Models\AiVisibilityResult;
use Shoptimised\AiVisibility\Services\CompetitorExtractor;
use Shoptimised\AiVisibility\Services\ConfidenceScorer;
use Shoptimised\AiVisibility\Services\VisibilityMatcher;
use Shoptimised\AiVisibility\Services\VisibilityResultParser;

function parser(): VisibilityResultParser
{
    return new VisibilityResultParser(new VisibilityMatcher, new ConfidenceScorer, new CompetitorExtractor);
}

function resultWith(array $raw): AiVisibilityResult
{
    // Non-persisted model; the array cast applies without a database connection.
    return new AiVisibilityResult(['raw_response' => $raw]);
}

function parseContext(array $o = []): array
{
    return array_merge([
        'retailer_domain' => 'gardenliving.example',
        'item_group_title' => 'rattan corner sofa sets',
        'category' => 'garden furniture',
        'product_urls' => ['https://gardenliving.example/rattan-corner-sofa-sets/grey'],
        'prompt_type' => 'generic_discovery',
    ], $o);
}

it('parses a surfaced response into the strongest match with competitors', function () {
    $parsed = parser()->parse(resultWith([
        'mode' => 'api',
        'success' => true,
        'text' => 'The best rattan corner sofa sets are from gardenliving.example, ahead of others.',
        'citations' => [
            ['url' => 'https://gardenliving.example/rattan-corner-sofa-sets/grey', 'domain' => 'gardenliving.example', 'position' => 1],
            ['url' => 'https://argos.co.uk/garden', 'domain' => 'argos.co.uk', 'position' => 2],
        ],
    ]), parseContext());

    expect($parsed->surfaced)->toBeTrue()
        ->and($parsed->matchType)->toBe(MatchType::ExactItemGroupAndUrl)
        ->and($parsed->confidenceScore)->toBe(100)
        ->and($parsed->citationPosition)->toBe(1)
        ->and($parsed->competitors)->toHaveCount(1)
        ->and($parsed->competitors[0]->domain)->toBe('argos.co.uk');
});

it('flags a variant gap when a variant-led prompt does not surface', function () {
    $parsed = parser()->parse(resultWith([
        'mode' => 'api',
        'text' => 'Argos and Currys both list these in several colours.',
        'citations' => [['url' => 'https://argos.co.uk/x', 'domain' => 'argos.co.uk', 'position' => 1]],
    ]), parseContext(['prompt_type' => 'variant_led']));

    expect($parsed->surfaced)->toBeFalse()
        ->and($parsed->variantGaps)->not->toBe([]);
});

it('treats a pending manual response as not surfaced without fabricating', function () {
    $parsed = parser()->parse(resultWith(['mode' => 'pending', 'text' => '', 'citations' => []]), parseContext());

    expect($parsed->surfaced)->toBeFalse()
        ->and($parsed->matchType)->toBe(MatchType::None)
        ->and($parsed->confidenceScore)->toBe(0)
        ->and($parsed->responseSummary)->toBe('Awaiting manual evidence.');
});
