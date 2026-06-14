<?php

use Shoptimised\AiVisibility\Services\CompetitorExtractor;

it('extracts citation-backed competitors, excluding the retailer and generic domains', function () {
    $competitors = (new CompetitorExtractor)->extract(
        'Several retailers stock these.',
        [
            ['url' => 'https://www.argos.co.uk/x', 'domain' => 'www.argos.co.uk', 'position' => 1],
            ['url' => 'https://gardenliving.example/x', 'domain' => 'gardenliving.example', 'position' => 2],
            ['url' => 'https://en.wikipedia.org/wiki/Sofa', 'domain' => 'wikipedia.org', 'position' => 3],
            ['url' => 'https://www.argos.co.uk/y', 'domain' => 'argos.co.uk', 'position' => 4],
        ],
        ['retailer_domain' => 'gardenliving.example'],
    );

    expect($competitors)->toHaveCount(1)
        ->and($competitors[0]->domain)->toBe('argos.co.uk');
});

it('never invents competitors from prose without citations', function () {
    $competitors = (new CompetitorExtractor)->extract(
        'John Lewis and Currys are popular choices.',
        [],
        ['retailer_domain' => 'gardenliving.example'],
    );

    expect($competitors)->toBe([]);
});
