<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_visibility_prompts', function (Blueprint $table) {
            // Where a prompt's text came from. Currently only meaningful for
            // qna_led prompts: 'feed_qna' (taken verbatim from the feed's Q&A)
            // vs 'discovered_faq' (discovered for a feed with no Q&A using the
            // GTIN + item group title). Null for generated prompt types.
            $table->string('source')->nullable()->after('prompt_type');
        });
    }

    public function down(): void
    {
        Schema::table('ai_visibility_prompts', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
