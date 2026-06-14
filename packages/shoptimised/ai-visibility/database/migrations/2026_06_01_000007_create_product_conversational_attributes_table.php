<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_conversational_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('attribute_type');
            $table->string('attribute_key')->nullable();
            $table->jsonb('attribute_value')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->nullable();
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->boolean('live_in_feed')->default(false);
            $table->timestamps();

            $table->index(['retailer_id', 'product_id', 'attribute_type'], 'pca_retailer_product_type_idx');
            $table->index(['retailer_id', 'attribute_type', 'live_in_feed'], 'pca_retailer_type_live_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_conversational_attributes');
    }
};
