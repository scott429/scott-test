<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_visibility_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained('ai_visibility_results')->cascadeOnDelete();
            $table->string('evidence_type');
            $table->text('storage_path')->nullable();
            $table->text('external_url')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index('result_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_visibility_evidence');
    }
};
