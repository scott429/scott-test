<?php

namespace Shoptimised\AiVisibility\DataObjects;

use Shoptimised\AiVisibility\Enums\MatchType;

/**
 * Output of the VisibilityResultParser (Phase 3). Defined now so jobs and the
 * result schema are stable; population logic lands in Phase 3.
 *
 * @property CompetitorMention[] $competitors
 * @property RecommendedAction[] $recommendedActions
 */
final readonly class ParsedVisibilityResult
{
    public function __construct(
        public bool $surfaced,
        public MatchType $matchType,
        public int $confidenceScore,
        public ?int $mentionPosition = null,
        public ?int $citationPosition = null,
        public ?string $surfacedUrl = null,
        public ?string $surfacedTitle = null,
        public array $competitors = [],
        public array $qnaThemeGaps = [],
        public array $variantGaps = [],
        public array $relatedProductGaps = [],
        public array $documentGaps = [],
        public array $recommendedActions = [],
        public ?string $responseSummary = null,
    ) {}

    public static function notSurfaced(): self
    {
        return new self(
            surfaced: false,
            matchType: MatchType::None,
            confidenceScore: 0,
        );
    }
}
