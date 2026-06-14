<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_visibility_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->foreignId('feed_id')->constrained('feeds')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->jsonb('platforms')->nullable();
            $table->jsonb('selected_filters')->nullable();
            $table->unsignedInteger('total_item_groups')->default(0);
            $table->unsignedInteger('total_prompts')->default(0);
            $table->unsignedInteger('completed_prompts')->default(0);
            $table->unsignedInteger('failed_prompts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['retailer_id', 'status']);
            $table->index('feed_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_visibility_batches');
    }
};
