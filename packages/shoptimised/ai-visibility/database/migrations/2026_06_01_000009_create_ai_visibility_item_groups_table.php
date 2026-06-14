<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_visibility_item_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('ai_visibility_batches')->cascadeOnDelete();
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->foreignId('feed_id')->constrained('feeds')->cascadeOnDelete();
            $table->string('item_group_id')->nullable();
            $table->string('item_group_title')->nullable();
            $table->foreignId('representative_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->text('representative_product_url')->nullable();
            $table->string('brand')->nullable();
            $table->string('category')->nullable();
            $table->unsignedInteger('variant_count')->default(0);
            $table->unsignedBigInteger('total_impressions')->default(0);
            $table->unsignedBigInteger('total_clicks')->default(0);
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->unsignedInteger('zero_click_variant_count')->default(0);
            $table->decimal('ai_visibility_score', 5, 2)->nullable();
            $table->decimal('surfaced_rate', 5, 2)->nullable();
            $table->decimal('average_position', 6, 2)->nullable();
            $table->jsonb('top_competitors')->nullable();
            $table->jsonb('recommended_actions')->nullable();
            $table->timestamps();

            $table->index('batch_id');
            $table->index(['retailer_id', 'item_group_title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_visibility_item_groups');
    }
};
