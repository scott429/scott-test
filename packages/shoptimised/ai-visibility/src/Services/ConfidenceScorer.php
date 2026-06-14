<?php

namespace Shoptimised\AiVisibility\Services;

use Shoptimised\AiVisibility\DataObjects\MatchSignals;

/**
 * Maps match signals to a 0-100 confidence that the retailer's item group truly
 * surfaced. Highest applicable rule wins (see build guide §11). The semantic band
 * interpolates 50-75 by similarity so "looks like the family" never scores as
 * high as a hard URL/title match.
 */
final class ConfidenceScorer
{
    public function score(MatchSignals $s): int
    {
        return match (true) {
            $s->exactItemGroupTitle && $s->retailerUrlCited => 100,
            $s->productUrlCited => 95,
            $s->exactItemGroupTitle => 85,
            $s->retailerDomainOnly => 70,
            $s->semanticProductFamily => $this->lerp($s->semanticSimilarity, 50, 75),
            $s->categoryMentionOnly => 30,
            default => 0,
        };
    }

    /** Map a 0..1 similarity into an integer band [lo, hi]. */
    private function lerp(float $t, int $lo, int $hi): int
    {
        return (int) round($lo + max(0.0, min(1.0, $t)) * ($hi - $lo));
    }
}
