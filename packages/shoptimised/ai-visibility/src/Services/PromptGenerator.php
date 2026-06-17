<?php

namespace Shoptimised\AiVisibility\Services;

use Shoptimised\AiVisibility\Enums\PromptType;

/**
 * Builds a deterministic, controlled set of prompts for one item group.
 *
 * Pure: takes a context array (assembled by the job from the item group, its
 * products and conversational attributes) and returns prompt specs. Determinism
 * matters so monthly re-runs compare like-for-like.
 *
 * Context keys:
 *   item_group_title (required), brand, category, product_type,
 *   variant_options[], price_min, price_max, currency,
 *   important_attributes[], use_cases[], problems[],
 *   questions[], questions_source ('feed_qna'|'discovered_faq')
 *
 * @phpstan-type PromptSpec array{prompt_text:string,prompt_type:string,source:?string,country:string,language:string}
 */
class PromptGenerator
{
    public function generate(array $context, array $options = []): array
    {
        $title = trim((string) ($context['item_group_title'] ?? ''));
        if ($title === '') {
            return [];
        }

        $limit = (int) ($options['limit'] ?? config('ai_visibility.limits.default_prompts_per_item_group', 10));
        $country = (string) ($options['country'] ?? config('ai_visibility.defaults.country', 'GB'));
        $language = (string) ($options['language'] ?? config('ai_visibility.defaults.language', 'en'));
        $region = $this->countryLabel($country);
        $currency = (string) ($context['currency'] ?? '£');

        $brand = $context['brand'] ?? null;
        $attributes = array_values(array_filter((array) ($context['important_attributes'] ?? [])));
        $useCases = array_values(array_filter((array) ($context['use_cases'] ?? [])));
        $problems = array_values(array_filter((array) ($context['problems'] ?? [])));
        $variants = array_values(array_filter((array) ($context['variant_options'] ?? [])));
        $questions = array_values(array_filter(array_map(
            fn ($q) => trim((string) $q),
            (array) ($context['questions'] ?? []),
        )));
        $questionsSource = $questions === []
            ? null
            : (string) ($context['questions_source'] ?? 'feed_qna');
        $priceMax = $context['price_max'] ?? null;

        // Ordered candidate prompts. Types lacking source data are skipped rather
        // than fabricated.
        $candidates = [];

        $candidates[] = [PromptType::GenericDiscovery, "Best {$title} to buy online in the {$region}"];
        $candidates[] = [PromptType::CommercialIntent, "Where can I buy {$title} online in the {$region}?"];

        // Buyer Q&A from the product feed, tested verbatim so we can report which
        // questions actually surface the retailer.
        foreach ($questions as $question) {
            $candidates[] = [PromptType::QnaLed, $question];
        }

        if ($priceMax !== null) {
            $rounded = (int) ceil(((float) $priceMax) / 10) * 10;
            $candidates[] = [PromptType::PriceLed, "Best {$title} under {$currency}{$rounded}"];
        }

        if ($brand) {
            $candidates[] = [PromptType::Comparison, "Best alternatives to {$brand} {$title}"];
        }

        foreach ($attributes as $attr) {
            $candidates[] = [PromptType::AttributeLed, "Best {$title} for {$attr}"];
        }

        foreach ($useCases as $useCase) {
            $candidates[] = [PromptType::UseCase, "Which {$title} is best for {$useCase}?"];
        }

        foreach ($problems as $problem) {
            $candidates[] = [PromptType::ProblemLed, "What {$title} is good for {$problem}?"];
        }

        foreach ($variants as $variant) {
            $candidates[] = [PromptType::VariantLed, "{$title} available in {$variant}"];
        }

        // De-duplicate by prompt text, then cap to the limit.
        $seen = [];
        $prompts = [];
        foreach ($candidates as [$type, $text]) {
            $key = mb_strtolower($text);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $prompts[] = [
                'prompt_text' => $text,
                'prompt_type' => $type->value,
                'source' => $type === PromptType::QnaLed ? $questionsSource : null,
                'country' => $country,
                'language' => $language,
            ];
            if (count($prompts) >= $limit) {
                break;
            }
        }

        return $prompts;
    }

    protected function countryLabel(string $code): string
    {
        return match (strtoupper($code)) {
            'GB', 'UK' => 'UK',
            'US' => 'US',
            'IE' => 'Ireland',
            'AU' => 'Australia',
            'CA' => 'Canada',
            default => strtoupper($code),
        };
    }
}
