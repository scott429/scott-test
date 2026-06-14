<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_visibility_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained('ai_visibility_results')->cascadeOnDelete();
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->string('competitor_domain');
            $table->string('competitor_name')->nullable();
            $table->text('surfaced_url')->nullable();
            $table->text('surfaced_title')->nullable();
            $table->unsignedInteger('mention_position')->nullable();
            $table->unsignedInteger('citation_position')->nullable();
            $table->timestamps();

            $table->index('result_id');
            $table->index(['retailer_id', 'competitor_domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_visibility_competitors');
    }
};
