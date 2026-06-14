<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_performance_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->foreignId('feed_id')->constrained('feeds')->cascadeOnDelete();
            $table->string('product_id_external');
            $table->date('date');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('conversions', 10, 2)->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('cpc', 10, 4)->default(0);
            $table->decimal('conversion_rate', 8, 4)->default(0);
            $table->decimal('roas', 10, 4)->default(0);
            $table->timestamps();

            $table->unique(['feed_id', 'product_id_external', 'date'], 'ppd_feed_product_date_unique');
            $table->index(['retailer_id', 'feed_id', 'date']);
            $table->index(['retailer_id', 'clicks']);
            $table->index(['retailer_id', 'conversions']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_performance_daily');
    }
};
