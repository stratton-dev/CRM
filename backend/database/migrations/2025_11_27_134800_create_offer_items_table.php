<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('offer_items', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnUpdate()->cascadeOnDelete();

            // Content
            $table->string('name');
            $table->text('description')->nullable();

            // Numbers
            $table->decimal('qty', 12, 3)->default(1);
            $table->string('unit', 16)->default('szt');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount_percent', 5, 2)->nullable(); // e.g. 10.00
            $table->decimal('vat_rate', 5, 2)->default(23.00); // e.g. 23.00

            // Line totals (denormalized)
            $table->decimal('line_net', 14, 2)->nullable();
            $table->decimal('line_vat', 14, 2)->nullable();
            $table->decimal('line_gross', 14, 2)->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['offer_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_items');
    }
};
