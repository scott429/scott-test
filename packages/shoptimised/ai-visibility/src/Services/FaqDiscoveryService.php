<?php

namespace Shoptimised\AiVisibility\Services;

use Shoptimised\AiVisibility\Providers\ProviderRegistry;

/**
 * Discovers the FAQs buyers most commonly ask about a product when the feed
 * carries no Q&A of its own. Asks a search-grounded provider, using the GTIN and
 * item group title to anchor the query, then parses the answer into clean
 * question strings the prompt generator can test as qna_led prompts.
 *
 * Best-effort by design: if no real provider is enabled, or the call fails, it
 * returns an empty list rather than fabricating questions.
 *
 * Context keys: item_group_title (required), gtin, brand, category, country.
 */
class FaqDiscoveryService
{
    public function __construct(protected ProviderRegistry $registry) {}

    /**
     * @param  array{item_group_title?:string,gtin?:?string,brand?:?string,category?:?string,country?:string}  $context
     * @return string[] discovered buyer questions (deduped, capped)
     */
    public function discover(array $context, array $options = []): array
    {
        if (! (bool) config('ai_visibility.faq_discovery.enabled', true)) {
            return [];
        }

        $title = trim((string) ($context['item_group_title'] ?? ''));
        if ($title === '') {
            return [];
        }

        $platform = $this->resolvePlatform($options['platform'] ?? config('ai_visibility.faq_discovery.platform'));
        if ($platform === null) {
            return [];
        }

        $max = (int) ($options['max_questions'] ?? config('ai_visibility.faq_discovery.max_questions', 6));
        $max = max(1, $max);

        $prompt = $this->buildPrompt($title, $context, $max);

        $response = $this->registry->resolve($platform)->runPrompt($prompt, [
            'country' => (string) ($context['country'] ?? config('ai_visibility.defaults.country', 'GB')),
            'item_group_title' => $title,
        ]);

        if (! $response->success || $response->mode !== 'api' || trim($response->text) === '') {
            return [];
        }

        return $this->parseQuestions($response->text, $max);
    }

    /**
     * Resolve the discovery platform: an explicit choice if it's a real,
     * enabled provider, otherwise the first enabled non-manual provider.
     */
    protected function resolvePlatform(?string $preferred): ?string
    {
        if ($preferred && $preferred !== 'manual' && $this->registry->isEnabled($preferred)) {
            return $preferred;
        }

        foreach ($this->registry->enabledPlatforms() as $platform) {
            if ($platform !== 'manual') {
                return $platform;
            }
        }

        return null;
    }

    /** @param  array<string,mixed>  $context */
    protected function buildPrompt(string $title, array $context, int $max): string
    {
        $anchors = [];
        if (! empty($context['brand'])) {
            $anchors[] = "brand {$context['brand']}";
        }
        if (! empty($context['gtin'])) {
            $anchors[] = "GTIN {$context['gtin']}";
        }
        if (! empty($context['category'])) {
            $anchors[] = "category {$context['category']}";
        }

        $detail = $anchors === [] ? '' : ' ('.implode(', ', $anchors).')';

        return "List the {$max} most common questions online shoppers ask before buying {$title}{$detail}. "
            .'Base it on questions that frequently appear in shopping results and product Q&A. '
            .'Return only the questions, one per line, each phrased as a natural buyer question ending in a question mark. '
            .'Do not number them or add any commentary.';
    }

    /**
     * Parse free-text provider output into clean question strings.
     *
     * @return string[]
     */
    public function parseQuestions(string $text, int $max): array
    {
        $questions = [];
        $seen = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            // Strip leading list markers (1. , - , * , •) and surrounding noise.
            $line = preg_replace('/^\s*(?:[-*•]|\d+[.)])\s*/u', '', trim($line)) ?? '';
            $line = trim($line, " \t\"'");

            if ($line === '' || ! str_contains($line, '?')) {
                continue;
            }

            // Keep only up to and including the first question mark.
            $line = trim(substr($line, 0, strpos($line, '?') + 1));

            // Skip fragments that aren't really questions.
            if (mb_strlen($line) < 8) {
                continue;
            }

            $key = mb_strtolower($line);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $questions[] = $line;

            if (count($questions) >= $max) {
                break;
            }
        }

        return $questions;
    }
}
