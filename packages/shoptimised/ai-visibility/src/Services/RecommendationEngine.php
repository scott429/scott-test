<?php

namespace Shoptimised\AiVisibility\Services;

use Shoptimised\AiVisibility\DataObjects\RecommendedAction;
use Shoptimised\AiVisibility\Enums\ActionType;

/**
 * Maps an item group's visibility gaps to feed-action recommendations. Pure:
 * the job adapts models into the run array shape below, the engine returns
 * RecommendedAction DTOs, the job persists them.
 *
 * Run shape: ['prompt_type'=>string, 'surfaced'=>bool,
 *             'competitor_surfaced'=>bool, 'document_gap'=>bool]
 *
 * Context: ['surfaced_rate'=>float, 'zero_click_variant_count'=>int,
 *           'item_group_title'=>string]
 */
final class RecommendationEngine
{
    /**
     * @param  iterable<int,array>  $runs
     * @return RecommendedAction[]
     */
    public function forItemGroup(array $context, iterable $runs): array
    {
        $runs = is_array($runs) ? $runs : iterator_to_array($runs);
        $title = (string) ($context['item_group_title'] ?? 'this item group');
        $priority = $this->priority((float) ($context['surfaced_rate'] ?? 0));

        // Per prompt-type: did the retailer ever surface? did a competitor?
        $byType = [];
        $documentGap = false;
        foreach ($runs as $r) {
            $type = (string) ($r['prompt_type'] ?? '');
            $byType[$type]['surfaced'] = ($byType[$type]['surfaced'] ?? false) || ! empty($r['surfaced']);
            $byType[$type]['competitor'] = ($byType[$type]['competitor'] ?? false) || ! empty($r['competitor_surfaced']);
            $documentGap = $documentGap || ! empty($r['document_gap']);
        }

        $missed = fn (string $type) => isset($byType[$type]) && ! $byType[$type]['surfaced'];
        $rivalsWon = fn (string $type) => isset($byType[$type]) && ! $byType[$type]['surfaced'] && $byType[$type]['competitor'];

        $actions = [];

        if ($missed('variant_led') || (int) ($context['zero_click_variant_count'] ?? 0) > 0 && $missed('variant_led')) {
            $actions[] = new RecommendedAction(
                ActionType::AddVariantOption, $priority,
                "Variant-led prompts didn't surface {$title}; rivals did. Add the missing colour/size/capacity variants to the feed.",
            );
        }

        foreach (['qna_led', 'attribute_led', 'use_case', 'problem_led'] as $type) {
            if ($rivalsWon($type)) {
                $actions[] = new RecommendedAction(
                    ActionType::AddQna, $priority,
                    "Buyer questions for {$title} surfaced competitors but not you. Add or improve the Q&A covering those questions.",
                );
                break; // one Q&A action per group is enough to action
            }
        }

        if ($documentGap) {
            $actions[] = new RecommendedAction(
                ActionType::AddDocumentLink, $this->softer($priority),
                "Competitors surfaced for {$title} with spec sheets / guides cited as sources. Add document links to the feed.",
            );
        }

        if ($missed('comparison')) {
            $actions[] = new RecommendedAction(
                ActionType::AddRelatedProduct, $this->softer($priority),
                "Comparison prompts for {$title} surfaced rivals' ranges. Strengthen related-product links.",
            );
        }

        if ($missed('price_led')) {
            $actions[] = new RecommendedAction(
                ActionType::ReviewPricing, $this->softer($priority),
                "Price-led prompts for {$title} didn't surface. Review pricing competitiveness and price signals in the feed.",
            );
        }

        if ($missed('generic_discovery') || $missed('commercial_intent')) {
            $actions[] = new RecommendedAction(
                ActionType::ImproveItemGroupTitle, $priority,
                "Not surfaced for broad discovery prompts on {$title}. Improve the item group title and core descriptions.",
            );
        }

        return $actions;
    }

    private function priority(float $surfacedRate): string
    {
        return match (true) {
            $surfacedRate < 40 => 'high',
            $surfacedRate < 70 => 'medium',
            default => 'low',
        };
    }

    private function softer(string $priority): string
    {
        return match ($priority) {
            'high' => 'medium',
            'medium' => 'low',
            default => 'low',
        };
    }
}
