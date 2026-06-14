<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_visibility_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('ai_visibility_batches')->cascadeOnDelete();
            $table->foreignId('prompt_id')->constrained('ai_visibility_prompts')->cascadeOnDelete();
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->string('platform');
            $table->string('model_or_surface')->nullable();
            $table->unsignedInteger('run_number')->default(1);
            $table->jsonb('raw_response')->nullable();
            $table->text('response_summary')->nullable();
            $table->boolean('surfaced')->default(false);
            $table->string('match_type')->default('none');
            $table->unsignedSmallInteger('confidence_score')->default(0);
            $table->unsignedInteger('mention_position')->nullable();
            $table->unsignedInteger('citation_position')->nullable();
            $table->text('surfaced_url')->nullable();
            $table->text('surfaced_title')->nullable();
            $table->jsonb('competitors_surfaced')->nullable();
            $table->unsignedInteger('competitor_count')->default(0);
            $table->jsonb('qna_theme_gaps')->nullable();
            $table->jsonb('variant_gaps')->nullable();
            $table->jsonb('related_product_gaps')->nullable();
            $table->jsonb('document_gaps')->nullable();
            $table->jsonb('recommended_actions')->nullable();
            $table->text('evidence_url')->nullable();
            $table->timestamp('tested_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'prompt_id']);
            $table->index(['retailer_id', 'surfaced']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_visibility_results');
    }
};
