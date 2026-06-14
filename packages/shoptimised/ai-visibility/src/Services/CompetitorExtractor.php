<?php

namespace Shoptimised\AiVisibility\Services;

use Shoptimised\AiVisibility\DataObjects\CompetitorMention;

/**
 * Extracts competitor mentions from a response. Only citation-backed domains are
 * emitted — a real URL the provider actually returned — so we never assert a
 * hallucinated competitor as fact (build guide risk note). The retailer's own
 * domain and generic infrastructure/reference domains are excluded.
 */
final class CompetitorExtractor
{
    /** Domains that are never "competitors". */
    private const IGNORED = [
        'google.com', 'google.co.uk', 'bing.com', 'duckduckgo.com', 'yahoo.com',
        'wikipedia.org', 'youtube.com', 'reddit.com', 'quora.com', 'facebook.com',
        'instagram.com', 'twitter.com', 'x.com', 'pinterest.com', 'tiktok.com',
        'trustpilot.com', 'which.co.uk',
    ];

    /**
     * @param  array<int,array>  $citations
     * @param  array{retailer_domain?:string}  $context
     * @return CompetitorMention[]
     */
    public function extract(string $text, array $citations, array $context): array
    {
        $retailer = $this->rootDomain((string) ($context['retailer_domain'] ?? ''));
        $seen = [];
        $out = [];
        $rank = 0;

        foreach ($citations as $c) {
            $url = (string) ($c['url'] ?? '');
            $domain = $this->rootDomain((string) ($c['domain'] ?? $this->hostOf($url)));

            if ($domain === '' || in_array($domain, self::IGNORED, true)) {
                continue;
            }
            if ($retailer !== '' && str_contains($domain, $retailer)) {
                continue; // the retailer themselves
            }
            if (isset($seen[$domain])) {
                continue;
            }

            $seen[$domain] = true;
            $rank++;
            $out[] = new CompetitorMention(
                domain: $domain,
                name: $this->nameFromDomain($domain),
                url: $url ?: null,
                title: $c['title'] ?? null,
                mentionPosition: $rank,
                citationPosition: (int) ($c['position'] ?? $rank) ?: $rank,
            );
        }

        return $out;
    }

    private function nameFromDomain(string $domain): string
    {
        $label = explode('.', $domain)[0] ?? $domain;

        return ucfirst($label);
    }

    private function hostOf(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_HOST) ?: '');
    }

    /**
     * Multi-part public suffixes where the registrable domain needs three labels
     * (e.g. argos.co.uk, not co.uk). Covers the suffixes Shoptimised retailers use.
     */
    private const MULTI_PART_SUFFIXES = [
        'co.uk', 'org.uk', 'me.uk', 'ltd.uk', 'plc.uk', 'net.uk', 'sch.uk', 'gov.uk', 'ac.uk',
        'com.au', 'net.au', 'org.au', 'co.nz', 'co.za', 'com.br', 'co.jp', 'co.in', 'com.de',
    ];

    private function rootDomain(string $domain): string
    {
        $domain = mb_strtolower(trim($domain));
        if (str_contains($domain, '://')) {
            $domain = $this->hostOf($domain);
        }
        $domain = preg_replace('/^www\./', '', $domain) ?? $domain;
        $parts = array_values(array_filter(explode('.', $domain)));
        $count = count($parts);

        if ($count < 2) {
            return $domain;
        }

        // If the final two labels form a known multi-part suffix, keep three labels.
        $lastTwo = implode('.', array_slice($parts, -2));
        if ($count >= 3 && in_array($lastTwo, self::MULTI_PART_SUFFIXES, true)) {
            return implode('.', array_slice($parts, -3));
        }

        return $lastTwo;
    }
}
