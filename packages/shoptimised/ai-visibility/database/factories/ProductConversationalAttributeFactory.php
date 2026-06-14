<?php

namespace Shoptimised\AiVisibility\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Shoptimised\AiVisibility\Enums\AttributeType;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\ProductConversationalAttribute;
use Shoptimised\AiVisibility\Models\Retailer;

/**
 * @extends Factory<ProductConversationalAttribute>
 */
class ProductConversationalAttributeFactory extends Factory
{
    protected $model = ProductConversationalAttribute::class;

    public function definition(): array
    {
        return [
            'retailer_id' => Retailer::factory(),
            'product_id' => Product::factory(),
            'attribute_type' => fake()->randomElement(AttributeType::cases())->value,
            'attribute_key' => fake()->word(),
            'attribute_value' => ['value' => fake()->sentence()],
            'source' => fake()->randomElement(['feed', 'generated', 'manual']),
            'status' => 'active',
            'confidence_score' => fake()->numberBetween(40, 100),
            'live_in_feed' => fake()->boolean(60),
        ];
    }

    public function ofType(AttributeType $type): static
    {
        return $this->state(fn () => ['attribute_type' => $type->value]);
    }

    public function live(bool $live = true): static
    {
        return $this->state(fn () => ['live_in_feed' => $live]);
    }
}
