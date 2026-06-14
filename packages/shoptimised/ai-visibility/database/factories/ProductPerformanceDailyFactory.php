<?php

namespace Shoptimised\AiVisibility\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\ProductPerformanceDaily;
use Shoptimised\AiVisibility\Models\Retailer;

/**
 * @extends Factory<ProductPerformanceDaily>
 */
class ProductPerformanceDailyFactory extends Factory
{
    protected $model = ProductPerformanceDaily::class;

    public function definition(): array
    {
        $impressions = fake()->numberBetween(0, 5000);
        $clicks = (int) round($impressions * fake()->randomFloat(4, 0, 0.08));
        $cost = $clicks * fake()->randomFloat(2, 0.2, 1.5);
        $conversions = (int) round($clicks * fake()->randomFloat(4, 0, 0.05));
        $revenue = $conversions * fake()->randomFloat(2, 30, 400);

        return [
            'retailer_id' => Retailer::factory(),
            'feed_id' => Feed::factory(),
            'product_id_external' => 'SKU-'.fake()->bothify('########'),
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'impressions' => $impressions,
            'clicks' => $clicks,
            'cost' => round($cost, 2),
            'conversions' => $conversions,
            'revenue' => round($revenue, 2),
            'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : 0,
            'cpc' => $clicks > 0 ? round($cost / $clicks, 4) : 0,
            'conversion_rate' => $clicks > 0 ? round($conversions / $clicks, 4) : 0,
            'roas' => $cost > 0 ? round($revenue / $cost, 4) : 0,
        ];
    }
}
