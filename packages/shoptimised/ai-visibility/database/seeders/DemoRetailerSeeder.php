<?php

namespace Shoptimised\AiVisibility\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Shoptimised\AiVisibility\Enums\AttributeType;
use Shoptimised\AiVisibility\Enums\Role as RoleEnum;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\ProductConversationalAttribute;
use Shoptimised\AiVisibility\Models\ProductPerformanceDaily;
use Shoptimised\AiVisibility\Models\Retailer;

class DemoRetailerSeeder extends Seeder
{
    public function run(): void
    {
        $retailer = Retailer::factory()->create([
            'name' => 'Garden Living Co',
            'domain' => 'gardenliving.example',
        ]);

        // Shoptimised staff (no retailer) and retailer users.
        $admin = User::factory()->create([
            'name' => 'Shoptimised Admin',
            'email' => 'admin@shoptimised.example',
            'retailer_id' => null,
        ]);
        $admin->assignRole(RoleEnum::ShoptimisedAdmin->value);

        $analyst = User::factory()->create([
            'name' => 'Shoptimised Analyst',
            'email' => 'analyst@shoptimised.example',
            'retailer_id' => null,
        ]);
        $analyst->assignRole(RoleEnum::ShoptimisedAnalyst->value);
        $analyst->assignedRetailers()->syncWithoutDetaching([$retailer->id]);

        $retailerAdmin = User::factory()->create([
            'name' => 'Retailer Admin',
            'email' => 'owner@gardenliving.example',
            'retailer_id' => $retailer->id,
        ]);
        $retailerAdmin->assignRole(RoleEnum::RetailerAdmin->value);

        $retailerViewer = User::factory()->create([
            'name' => 'Retailer Viewer',
            'email' => 'viewer@gardenliving.example',
            'retailer_id' => $retailer->id,
        ]);
        $retailerViewer->assignRole(RoleEnum::RetailerViewer->value);

        $feed = Feed::factory()->create([
            'retailer_id' => $retailer->id,
            'name' => 'Outdoor Living feed',
            'merchant_center_id' => '5512347',
        ]);

        // Coherent item groups with several variants each.
        $itemGroups = [
            ['title' => 'Rattan corner sofa sets', 'brand' => 'GardenLux', 'variants' => ['Grey', 'Brown', 'Black'], 'price' => 699],
            ['title' => 'Cantilever parasols', 'brand' => 'ShadeMaster', 'variants' => ['2.5m', '3m', '3.5m'], 'price' => 149],
            ['title' => 'Fire pit tables', 'brand' => 'EmberCo', 'variants' => ['Round', 'Square'], 'price' => 329],
            ['title' => 'Egg chairs', 'brand' => 'NestLiving', 'variants' => ['Single', 'Double'], 'price' => 259],
            ['title' => 'Garden storage boxes', 'brand' => 'StoreEasy', 'variants' => ['270L', '420L', '630L'], 'price' => 89],
        ];

        foreach ($itemGroups as $group) {
            $itemGroupId = 'IG-'.Str::upper(Str::random(6));

            foreach ($group['variants'] as $i => $variant) {
                $sku = 'SKU-'.Str::upper(Str::random(8));

                $product = Product::factory()->create([
                    'retailer_id' => $retailer->id,
                    'feed_id' => $feed->id,
                    'product_id_external' => $sku,
                    'item_group_id' => $itemGroupId,
                    'item_group_title' => $group['title'],
                    'title' => $group['title'].' — '.$variant,
                    'brand' => $group['brand'],
                    'product_type' => $group['title'],
                    'price' => $group['price'] + ($i * 30),
                    'link' => 'https://'.$retailer->domain.'/'.Str::slug($group['title']).'/'.Str::slug($variant),
                ]);

                // Make one variant in each group a zero-click variant for filter testing.
                $zeroClick = $i === 0;
                ProductPerformanceDaily::factory()->create([
                    'retailer_id' => $retailer->id,
                    'feed_id' => $feed->id,
                    'product_id_external' => $sku,
                    'date' => now()->subDay()->format('Y-m-d'),
                    'impressions' => $zeroClick ? 120 : fake()->numberBetween(800, 6000),
                    'clicks' => $zeroClick ? 0 : fake()->numberBetween(30, 400),
                    'conversions' => $zeroClick ? 0 : fake()->numberBetween(0, 20),
                ]);

                // Conversational attributes: live item_group_title + variant_option,
                // but intentionally leave Q&A / related products / documents missing
                // for some groups so gap logic has something to find later.
                ProductConversationalAttribute::factory()
                    ->ofType(AttributeType::ItemGroupTitle)->live()
                    ->create(['retailer_id' => $retailer->id, 'product_id' => $product->id, 'attribute_value' => ['value' => $group['title']]]);

                ProductConversationalAttribute::factory()
                    ->ofType(AttributeType::VariantOption)->live()
                    ->create(['retailer_id' => $retailer->id, 'product_id' => $product->id, 'attribute_value' => ['option' => $variant]]);

                if (in_array($group['title'], ['Rattan corner sofa sets', 'Cantilever parasols'], true)) {
                    ProductConversationalAttribute::factory()
                        ->ofType(AttributeType::QuestionAndAnswer)->live()
                        ->create(['retailer_id' => $retailer->id, 'product_id' => $product->id]);
                }
            }
        }

        $this->command?->info('Seeded retailer "Garden Living Co" with '.$feed->products()->count().' products across '.count($itemGroups).' item groups.');
    }
}
