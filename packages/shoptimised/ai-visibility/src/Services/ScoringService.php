<?php

namespace Shoptimised\AiVisibility\Services;

/**
 * All visibility maths in one place so the weights are tunable centrally (and
 * later tenant-configurable). Framework-free: callers pass plain run arrays.
 *
 * Run shape: ['surfaced'=>bool, 'position'=>?int, 'cited'=>bool,
 *             'platform'=>string, 'confidence'=>int, 'competitor_count'=>int,
 *             'competitors'=>string[]]
 *
 * AI Visibility Score (per item group, 0-100), build guide §12:
 *   0.35 surfaced_rate + 0.20 position + 0.15 citation_presence
 * + 0.15 cross_platform_consistency + 0.10 competitor_gap + 0.05 avg_confidence
 */
final class ScoringService
{
    public const WEIGHTS = [
        'surfaced_rate' => 0.35,
        'position' => 0.20,
        'citation_presence' => 0.15,
        'cross_platform_consistency' => 0.15,
        'competitor_gap' => 0.10,
        'avg_confidence' => 0.05,
    ];

    /**
     * @param  iterable<int,array>  $runs
     * @return array{score:float,surfaced_rate:float,average_position:?float,citation_presence:float,cross_platform_consistency:float,competitor_gap:float,avg_confidence:float,total_runs:int,surfaced_runs:int}
     */
    public function visibilityScore(iterable $runs, int $platformsTested): array
    {
        $runs = is_array($runs) ? $runs : iterator_to_array($runs);
        $total = count($runs);

        if ($total === 0) {
            return [
                'score' => 0.0, 'surfaced_rate' => 0.0, 'average_position' => null,
                'citation_presence' => 0.0, 'cross_platform_consistency' => 0.0,
                'competitor_gap' => 0.0, 'avg_confidence' => 0.0,
                'total_runs' => 0, 'surfaced_runs' => 0,
            ];
        }

        $surfaced = array_values(array_filter($runs, fn ($r) => ! empty($r['surfaced'])));
        $surfacedCount = count($surfaced);

        $surfacedRate = $surfacedCount / $total * 100;
        $citationPresence = count(array_filter($runs, fn ($r) => ! empty($r['cited']))) / $total * 100;

        $positions = array_values(array_filter(array_map(
            fn ($r) => $r['position'] ?? null,
            $surfaced
        ), fn ($p) => $p !== null));
        $avgPosition = $positions ? array_sum($positions) / count($positions) : null;
        $positionComponent = $avgPosition !== null
            ? max(0.0, min(100.0, 100 / max(1.0, $avgPosition)))
            : ($surfacedCount > 0 ? 60.0 : 0.0);

        $platformsSurfaced = count(array_unique(array_map(
            fn ($r) => $r['platform'] ?? '',
            $surfaced
        )));
        $crossPlatform = $platformsTested > 0
            ? min(100.0, $platformsSurfaced / $platformsTested * 100)
            : 0.0;

        $avgCompetitors = array_sum(array_map(fn ($r) => (int) ($r['competitor_count'] ?? 0), $runs)) / $total;
        $competitorGap = max(0.0, min(100.0, 100 - $avgCompetitors * 10));

        $avgConfidence = $surfacedCount > 0
            ? array_sum(array_map(fn ($r) => (int) ($r['confidence'] ?? 0), $surfaced)) / $surfacedCount
            : 0.0;

        $score =
            self::WEIGHTS['surfaced_rate'] * $surfacedRate
            + self::WEIGHTS['position'] * $positionComponent
            + self::WEIGHTS['citation_presence'] * $citationPresence
            + self::WEIGHTS['cross_platform_consistency'] * $crossPlatform
            + self::WEIGHTS['competitor_gap'] * $competitorGap
            + self::WEIGHTS['avg_confidence'] * $avgConfidence;

        return [
            'score' => round($score, 2),
            'surfaced_rate' => round($surfacedRate, 2),
            'average_position' => $avgPosition !== null ? round($avgPosition, 2) : null,
            'citation_presence' => round($citationPresence, 2),
            'cross_platform_consistency' => round($crossPlatform, 2),
            'competitor_gap' => round($competitorGap, 2),
            'avg_confidence' => round($avgConfidence, 2),
            'total_runs' => $total,
            'surfaced_runs' => $surfacedCount,
        ];
    }

    /**
     * Per-platform surfaced rate weighted by citation presence — drives the
     * "best / weakest platform" callouts.
     *
     * @param  iterable<int,array>  $runs
     * @return array<string,array{surfaced_rate:float,citation_rate:float,score:float}>
     */
    public function platformVisibility(iterable $runs): array
    {
        $byPlatform = [];
        foreach ($runs as $r) {
            $byPlatform[$r['platform'] ?? 'unknown'][] = $r;
        }

        $out = [];
        foreach ($byPlatform as $platform => $rows) {
            $n = count($rows);
            $surfacedRate = count(array_filter($rows, fn ($r) => ! empty($r['surfaced']))) / $n * 100;
            $citationRate = count(array_filter($rows, fn ($r) => ! empty($r['cited']))) / $n * 100;
            $out[$platform] = [
                'surfaced_rate' => round($surfacedRate, 2),
                'citation_rate' => round($citationRate, 2),
                'score' => round($surfacedRate * 0.7 + $citationRate * 0.3, 2),
            ];
        }

        return $out;
    }

    /**
     * Competitor pressure = competitor surfaced rate − retailer surfaced rate,
     * clamped to 0-100. High = under threat.
     *
     * @param  iterable<int,array>  $runs
     */
    public function competitorPressure(iterable $runs): float
    {
        $runs = is_array($runs) ? $runs : iterator_to_array($runs);
        $total = count($runs);
        if ($total === 0) {
            return 0.0;
        }

        $retailerRate = count(array_filter($runs, fn ($r) => ! empty($r['surfaced']))) / $total * 100;
        $competitorRate = count(array_filter($runs, fn ($r) => (int) ($r['competitor_count'] ?? 0) > 0)) / $total * 100;

        return round(max(0.0, min(100.0, $competitorRate - $retailerRate)), 2);
    }

    /**
     * Aggregate competitor domains across runs, most-surfaced first.
     *
     * @param  iterable<int,array>  $runs
     * @return array<int,array{domain:string,count:int}>
     */
    public function topCompetitors(iterable $runs, int $limit = 5): array
    {
        $counts = [];
        foreach ($runs as $r) {
            foreach ((array) ($r['competitors'] ?? []) as $domain) {
                $counts[$domain] = ($counts[$domain] ?? 0) + 1;
            }
        }
        arsort($counts);

        $out = [];
        foreach (array_slice($counts, 0, $limit, true) as $domain => $count) {
            $out[] = ['domain' => $domain, 'count' => $count];
        }

        return $out;
    }
}
