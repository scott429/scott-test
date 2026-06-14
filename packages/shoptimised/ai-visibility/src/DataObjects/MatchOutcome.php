<?php

namespace Shoptimised\AiVisibility\DataObjects;

use Shoptimised\AiVisibility\Enums\MatchType;

/**
 * Full result of matching one response against a retailer's item group:
 * the scorer signals plus the resolved match_type, positions and surfaced URL.
 */
final readonly class MatchOutcome
{
    public function __construct(
        public MatchSignals $signals,
        public MatchType $matchType,
        public bool $surfaced,
        public ?int $mentionPosition = null,
        public ?int $citationPosition = null,
        public ?string $surfacedUrl = null,
        public ?string $surfacedTitle = null,
    ) {}
}
