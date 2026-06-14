<?php

use Shoptimised\AiVisibility\DataObjects\MatchSignals;
use Shoptimised\AiVisibility\Services\ConfidenceScorer;

function score(array $overrides): int
{
    return (new ConfidenceScorer)->score(new MatchSignals(...$overrides));
}

it('scores the confidence ladder, highest wins', function () {
    expect(score(['exactItemGroupTitle' => true, 'retailerUrlCited' => true]))->toBe(100);
    expect(score(['productUrlCited' => true]))->toBe(95);
    expect(score(['exactItemGroupTitle' => true]))->toBe(85);
    expect(score(['retailerDomainOnly' => true]))->toBe(70);
    expect(score(['categoryMentionOnly' => true]))->toBe(30);
    expect(score([]))->toBe(0);
});

it('interpolates the semantic band 50-75 by similarity', function () {
    expect(score(['semanticProductFamily' => true, 'semanticSimilarity' => 0.0]))->toBe(50);
    expect(score(['semanticProductFamily' => true, 'semanticSimilarity' => 0.5]))->toBe(63);
    expect(score(['semanticProductFamily' => true, 'semanticSimilarity' => 1.0]))->toBe(75);
});
