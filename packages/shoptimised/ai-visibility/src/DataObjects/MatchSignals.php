<?php

namespace Shoptimised\AiVisibility\DataObjects;

/**
 * The boolean/scalar signals the ConfidenceScorer maps to a 0-100 score.
 * Produced by VisibilityMatcher. "highest wins" — see ConfidenceScorer.
 */
final readonly class MatchSignals
{
    public function __construct(
        public bool $exactItemGroupTitle = false,
        public bool $retailerUrlCited = false,
        public bool $productUrlCited = false,
        public bool $retailerDomainOnly = false,
        public bool $semanticProductFamily = false,
        public float $semanticSimilarity = 0.0,
        public bool $categoryMentionOnly = false,
    ) {}
}
