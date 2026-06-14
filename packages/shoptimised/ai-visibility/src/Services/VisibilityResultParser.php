<?php

namespace Shoptimised\AiVisibility\Services;

use Shoptimised\AiVisibility\DataObjects\ParsedVisibilityResult;
use Shoptimised\AiVisibility\Enums\MatchType;
use Shoptimised\AiVisibility\Models\AiVisibilityResult;

/**
 * Turns one stored raw_response into a ParsedVisibilityResult. Re-runnable
 * against the persisted response without re-billing any provider. Pending/manual
 * responses parse to "not surfaced" rather than fabricating a result.
 */
final class VisibilityResultParser
{
    public function __construct(
        private VisibilityMatcher $matcher,
        private ConfidenceScorer $scorer,
        private CompetitorExtractor $competitors,
    ) {}

    /**
     * @param  array{retailer_domain?:string,item_group_title?:string,category?:string,product_urls?:array<int,string>,prompt_type?:string}  $context
     */
    public function parse(AiVisibilityResult $result, array $context): ParsedVisibilityResult
    {
        $raw = (array) $result->raw_response;
        $mode = $raw['mode'] ?? 'api';
        $text = (string) ($raw['text'] ?? '');
        $citations = array_values((array) ($raw['citations'] ?? []));

        if ($mode === 'pending' || ($text === '' && $citations === [])) {
            return new ParsedVisibilityResult(
                surfaced: false,
                matchType: MatchType::None,
                confidenceScore: 0,
                responseSummary: 'Awaiting manual evidence.',
            );
        }

        $outcome = $this->matcher->match($text, $citations, $context);
        $confidence = $this->scorer->score($outcome->signals);
        $competitors = $this->competitors->extract($text, $citations, $context);

        $promptType = (string) ($context['prompt_type'] ?? '');
        $competitorSurfaced = $competitors !== [];

        return new ParsedVisibilityResult(
            surfaced: $outcome->surfaced,
            matchType: $outcome->matchType,
            confidenceScore: $confidence,
            mentionPosition: $outcome->mentionPosition,
            citationPosition: $outcome->citationPosition,
            surfacedUrl: $outcome->surfacedUrl,
            surfacedTitle: $outcome->surfacedTitle,
            competitors: $competitors,
            qnaThemeGaps: $this->qnaGap($outcome->surfaced, $promptType, $competitorSurfaced, $text),
            variantGaps: $this->variantGap($outcome->surfaced, $promptType, $text),
            relatedProductGaps: $this->relatedGap($outcome->surfaced, $promptType, $text),
            documentGaps: $this->documentGap($citations),
            responseSummary: mb_substr(trim($text), 0, 500) ?: null,
        );
    }

    private function qnaGap(bool $surfaced, string $type, bool $competitorSurfaced, string $text): array
    {
        $themeTypes = ['attribute_led', 'use_case', 'problem_led'];

        return (! $surfaced && $competitorSurfaced && in_array($type, $themeTypes, true))
            ? [['theme' => $type, 'evidence' => mb_substr($text, 0, 160)]]
            : [];
    }

    private function variantGap(bool $surfaced, string $type, string $text): array
    {
        return (! $surfaced && $type === 'variant_led')
            ? [['theme' => 'variant', 'evidence' => mb_substr($text, 0, 160)]]
            : [];
    }

    private function relatedGap(bool $surfaced, string $type, string $text): array
    {
        return (! $surfaced && $type === 'comparison')
            ? [['theme' => 'comparison', 'evidence' => mb_substr($text, 0, 160)]]
            : [];
    }

    /** Competitor citations that look like spec sheets / guides / manuals. */
    private function documentGap(array $citations): array
    {
        $docHints = ['.pdf', '/manual', '/guide', '/spec', 'datasheet', 'assembly', 'instructions'];
        $gaps = [];

        foreach ($citations as $c) {
            $url = mb_strtolower((string) ($c['url'] ?? ''));
            foreach ($docHints as $hint) {
                if ($url !== '' && str_contains($url, $hint)) {
                    $gaps[] = ['url' => $c['url'], 'hint' => ltrim($hint, '/.')];
                    break;
                }
            }
        }

        return $gaps;
    }
}
