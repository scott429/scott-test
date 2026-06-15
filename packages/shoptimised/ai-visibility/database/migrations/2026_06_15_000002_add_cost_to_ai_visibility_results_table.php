<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_visibility_results', function (Blueprint $table) {
            $table->decimal('cost_usd', 12, 6)->nullable()->after('confidence_score');
            $table->unsignedInteger('total_tokens')->nullable()->after('cost_usd');
        });
    }

    public function down(): void
    {
        Schema::table('ai_visibility_results', function (Blueprint $table) {
            $table->dropColumn(['cost_usd', 'total_tokens']);
        });
    }
};
