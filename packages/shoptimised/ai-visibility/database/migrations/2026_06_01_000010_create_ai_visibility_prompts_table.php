<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_visibility_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('ai_visibility_batches')->cascadeOnDelete();
            $table->foreignId('item_group_visibility_id')->constrained('ai_visibility_item_groups')->cascadeOnDelete();
            // retailer_id is not in the original spec; added for query scoping + isolation.
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->text('prompt_text');
            $table->string('prompt_type');
            $table->string('platform')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('language', 8)->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('run_count')->default(0);
            $table->timestamps();

            $table->index('batch_id');
            $table->index('item_group_visibility_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_visibility_prompts');
    }
};
