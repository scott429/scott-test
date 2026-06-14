<?php

namespace Shoptimised\AiVisibility\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\Retailer;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $title = fake()->words(3, true);

        return [
            'retailer_id' => Retailer::factory(),
            'feed_id' => Feed::factory(),
            'product_id_external' => 'SKU-'.Str::upper(Str::random(8)),
            'item_group_id' => 'IG-'.Str::upper(Str::random(6)),
            'item_group_title' => Str::title($title),
            'title' => Str::title($title),
            'description' => fake()->sentence(12),
            'brand' => fake()->company(),
            'product_type' => fake()->words(2, true),
            'google_product_category' => 'Home & Garden > Lawn & Garden',
            'link' => fake()->url(),
            'image_link' => fake()->imageUrl(),
            'price' => fake()->randomFloat(2, 20, 800),
            'availability' => 'in_stock',
            'gtin' => (string) fake()->ean13(),
            'mpn' => Str::upper(Str::random(10)),
            'custom_labels' => ['label_0' => fake()->randomElement(['bestseller', 'clearance', 'new'])],
        ];
    }
}
