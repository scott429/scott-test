<?php

namespace Shoptimised\AiVisibility\Services;

use Shoptimised\AiVisibility\DataObjects\MatchOutcome;
use Shoptimised\AiVisibility\DataObjects\MatchSignals;
use Shoptimised\AiVisibility\Enums\MatchType;

/**
 * Decides whether (and how strongly) a retailer's item group surfaced in one
 * response. Pure and deterministic: text + citations + context in, MatchOutcome
 * out. The semantic component uses token overlap as a stand-in for embeddings —
 * swap in a real similarity later without touching callers.
 *
 * @phpstan-type Citation array{url?:string,title?:string,domain?:string,position?:int}
 */
final class VisibilityMatcher
{
    public const SEMANTIC_THRESHOLD = 0.40;

    /**
     * @param  array<int,array>  $citations
     * @param  array{retailer_domain?:string,item_group_title?:string,category?:string,product_urls?:array<int,string>}  $context
     */
    public function match(string $text, array $citations, array $context): MatchOutcome
    {
        $haystack = mb_strtolower($text);
        $retailerDomain = $this->rootDomain((string) ($context['retailer_domain'] ?? ''));
        $title = mb_strtolower(trim((string) ($context['item_group_title'] ?? '')));
        $category = mb_strtolower(trim((string) ($context['category'] ?? '')));
        $productUrls = array_map([$this, 'normaliseUrl'], (array) ($context['product_urls'] ?? []));

        // --- citation-derived signals ---
        $retailerCitationPosition = null;
        $retailerUrl = null;
        $retailerTitle = null;
        $productUrlCited = false;

        foreach ($citations as $c) {
            $domain = $this->rootDomain((string) ($c['domain'] ?? $this->hostOf((string) ($c['url'] ?? ''))));
            $isRetailer = $retailerDomain !== '' && $domain !== '' && str_contains($domain, $retailerDomain);

            if ($isRetailer && $retailerCitationPosition === null) {
                $retailerCitationPosition = (int) ($c['position'] ?? 0) ?: null;
                $retailerUrl = $c['url'] ?? null;
                $retailerTitle = $c['title'] ?? null;
            }

            if ($isRetailer && in_array($this->normaliseUrl((string) ($c['url'] ?? '')), $productUrls, true)) {
                $productUrlCited = true;
            }
        }

        $retailerUrlCited = $retailerCitationPosition !== null;
        $retailerInText = $retailerDomain !== '' && str_contains($haystack, $retailerDomain);
        $retailerPresent = $retailerUrlCited || $retailerInText;

        $exactTitle = $title !== '' && str_contains($haystack, $title);
        $similarity = $this->similarity(trim($title.' '.$category), $haystack);
        $categoryMention = $category !== '' && str_contains($haystack, $category);

        // --- ladder (highest wins), mirroring the confidence map ---
        $retailerDomainOnly = $retailerPresent && ! $exactTitle && ! $productUrlCited;
        $semanticFamily = ! $retailerPresent && ! $exactTitle && ! $productUrlCited
            && $similarity >= self::SEMANTIC_THRESHOLD;
        $categoryOnly = ! $retailerPresent && ! $exactTitle && ! $productUrlCited
            && ! $semanticFamily && $categoryMention;

        $signals = new MatchSignals(
            exactItemGroupTitle: $exactTitle,
            retailerUrlCited: $retailerUrlCited,
            productUrlCited: $productUrlCited,
            retailerDomainOnly: $retailerDomainOnly,
            semanticProductFamily: $semanticFamily,
            semanticSimilarity: $similarity,
            categoryMentionOnly: $categoryOnly,
        );

        $matchType = match (true) {
            $exactTitle && $retailerUrlCited => MatchType::ExactItemGroupAndUrl,
            $productUrlCited => MatchType::ProductUrl,
            $exactTitle => MatchType::ExactItemGroup,
            $retailerDomainOnly => MatchType::RetailerDomain,
            $semanticFamily => MatchType::SemanticProductFamily,
            $categoryOnly => MatchType::CategoryOnly,
            default => MatchType::None,
        };

        return new MatchOutcome(
            signals: $signals,
            matchType: $matchType,
            surfaced: $matchType !== MatchType::None,
            mentionPosition: $retailerInText ? $this->textMentionRank($haystack, $retailerDomain, $citations) : null,
            citationPosition: $retailerCitationPosition,
            surfacedUrl: $retailerUrl,
            surfacedTitle: $retailerTitle,
        );
    }

    /** Token-overlap similarity (0..1): |A∩B| / |A| over the query tokens. */
    private function similarity(string $query, string $haystack): float
    {
        $queryTokens = array_unique(array_filter(preg_split('/\W+/', mb_strtolower($query)) ?: []));
        if ($queryTokens === []) {
            return 0.0;
        }

        $present = 0;
        foreach ($queryTokens as $token) {
            if (mb_strlen($token) >= 3 && str_contains($haystack, $token)) {
                $present++;
            }
        }

        return round($present / count($queryTokens), 4);
    }

    /** Rank of the retailer's first mention among all cited domains in order. */
    private function textMentionRank(string $haystack, string $retailerDomain, array $citations): int
    {
        $rank = 1;
        foreach ($citations as $c) {
            $domain = $this->rootDomain((string) ($c['domain'] ?? $this->hostOf((string) ($c['url'] ?? ''))));
            if ($domain !== '' && str_contains($domain, $retailerDomain)) {
                return $rank;
            }
            $rank++;
        }

        return 1;
    }

    private function hostOf(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_HOST) ?: '');
    }

    private function rootDomain(string $domain): string
    {
        $domain = mb_strtolower(trim($domain));
        $domain = preg_replace('/^www\./', '', $domain) ?? $domain;
        // strip a leading scheme if a full URL slipped through
        if (str_contains($domain, '://')) {
            $domain = $this->hostOf($domain);
            $domain = preg_replace('/^www\./', '', $domain) ?? $domain;
        }
        // reduce to the registrable-ish root (last two labels) for comparison
        $parts = array_filter(explode('.', $domain));
        if (count($parts) >= 2) {
            return implode('.', array_slice($parts, -2));
        }

        return $domain;
    }

    private function normaliseUrl(string $url): string
    {
        $url = mb_strtolower(trim($url));
        $host = $this->hostOf($url);
        $path = rtrim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');

        return $host !== '' ? preg_replace('/^www\./', '', $host).$path : $path;
    }
}
