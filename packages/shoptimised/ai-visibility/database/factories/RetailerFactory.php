<?php

namespace Shoptimised\AiVisibility\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Shoptimised\AiVisibility\Models\Retailer;

/**
 * @extends Factory<Retailer>
 */
class RetailerFactory extends Factory
{
    protected $model = Retailer::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'domain' => Str::slug($name).'.example',
            'status' => 'active',
        ];
    }
}
