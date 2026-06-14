<?php

namespace Shoptimised\AiVisibility\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Retailer;

/**
 * @extends Factory<Feed>
 */
class FeedFactory extends Factory
{
    protected $model = Feed::class;

    public function definition(): array
    {
        return [
            'retailer_id' => Retailer::factory(),
            'merchant_center_id' => (string) fake()->numberBetween(1000000, 9999999),
            'name' => fake()->words(2, true).' feed',
            'country' => 'GB',
            'currency' => 'GBP',
            'source_url' => fake()->url(),
            'last_imported_at' => now()->subDay(),
        ];
    }
}
