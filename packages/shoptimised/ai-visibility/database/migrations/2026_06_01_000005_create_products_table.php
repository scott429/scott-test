<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->foreignId('feed_id')->constrained('feeds')->cascadeOnDelete();
            $table->string('product_id_external');
            $table->string('item_group_id')->nullable();
            $table->string('item_group_title')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('brand')->nullable();
            $table->string('product_type')->nullable();
            $table->string('google_product_category')->nullable();
            $table->text('link')->nullable();
            $table->text('image_link')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('availability')->nullable();
            $table->string('gtin')->nullable();
            $table->string('mpn')->nullable();
            $table->jsonb('custom_labels')->nullable();
            $table->timestamps();

            $table->unique(['feed_id', 'product_id_external']);
            $table->index(['retailer_id', 'feed_id', 'item_group_id']);
            $table->index(['retailer_id', 'item_group_title']);
            $table->index(['retailer_id', 'brand']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX products_custom_labels_gin ON products USING gin (custom_labels)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
