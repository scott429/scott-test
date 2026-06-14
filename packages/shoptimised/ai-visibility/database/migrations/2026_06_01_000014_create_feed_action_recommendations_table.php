<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_action_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->foreignId('feed_id')->constrained('feeds')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('ai_visibility_batches')->nullOnDelete();
            $table->foreignId('item_group_visibility_id')->nullable()->constrained('ai_visibility_item_groups')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('action_type');
            $table->string('priority')->default('medium');
            $table->text('reason')->nullable();
            $table->text('evidence_summary')->nullable();
            $table->string('status')->default('suggested');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['retailer_id', 'status']);
            $table->index(['retailer_id', 'priority']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_action_recommendations');
    }
};
