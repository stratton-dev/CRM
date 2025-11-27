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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnUpdate()->restrictOnDelete();

            // Basic
            $table->string('number')->nullable()->unique(); // human readable identifier (optional)
            $table->enum('status', ['draft','sent','accepted','rejected','expired'])->default('draft');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('currency', 3)->default('PLN');

            // Totals (denormalized for quick listing/filters)
            $table->decimal('subtotal_net', 14, 2)->nullable();
            $table->decimal('total_vat', 14, 2)->nullable();
            $table->decimal('total_gross', 14, 2)->nullable();
            $table->decimal('total_discount', 14, 2)->nullable();

            // Free-form metadata (e.g., custom fields, attachments refs)
            $table->json('meta')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['valid_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
