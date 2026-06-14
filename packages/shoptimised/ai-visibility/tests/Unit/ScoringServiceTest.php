<?php

use Shoptimised\AiVisibility\Services\ScoringService;

function scoring(): ScoringService
{
    return new ScoringService;
}

it('returns zeros for no runs', function () {
    $m = scoring()->visibilityScore([], 3);
    expect($m['score'])->toBe(0.0)->and($m['surfaced_rate'])->toBe(0.0)
        ->and($m['average_position'])->toBeNull();
});

it('scores a perfect item group at 100', function () {
    $runs = [[
        'surfaced' => true, 'position' => 1, 'cited' => true,
        'platform' => 'openai', 'confidence' => 100, 'competitor_count' => 0, 'competitors' => [],
    ]];

    expect(scoring()->visibilityScore($runs, 1)['score'])->toBe(100.0);
});

it('computes the weighted score for a mixed item group', function () {
    $runs = [
        ['surfaced' => true, 'position' => 2, 'cited' => true, 'platform' => 'openai', 'confidence' => 80, 'competitor_count' => 1, 'competitors' => ['argos.co.uk']],
        ['surfaced' => false, 'position' => null, 'cited' => false, 'platform' => 'openai', 'confidence' => 0, 'competitor_count' => 2, 'competitors' => ['argos.co.uk', 'currys.co.uk']],
    ];

    $m = scoring()->visibilityScore($runs, 1);

    expect($m['surfaced_rate'])->toBe(50.0)
        ->and($m['average_position'])->toBe(2.0)
        ->and($m['score'])->toBe(62.5);
});

it('ranks top competitors by frequency', function () {
    $runs = [
        ['competitors' => ['argos.co.uk', 'currys.co.uk']],
        ['competitors' => ['argos.co.uk']],
    ];

    $top = scoring()->topCompetitors($runs);

    expect($top[0])->toBe(['domain' => 'argos.co.uk', 'count' => 2])
        ->and($top[1])->toBe(['domain' => 'currys.co.uk', 'count' => 1]);
});
