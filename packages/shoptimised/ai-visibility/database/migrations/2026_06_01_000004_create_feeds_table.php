<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->string('merchant_center_id')->nullable();
            $table->string('name');
            $table->string('country', 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->text('source_url')->nullable();
            $table->timestamp('last_imported_at')->nullable();
            $table->timestamps();

            $table->index('retailer_id');
            $table->index(['retailer_id', 'merchant_center_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeds');
    }
};
