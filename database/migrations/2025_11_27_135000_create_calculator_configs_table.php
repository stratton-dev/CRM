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
        Schema::create('calculator_configs', function (Blueprint $table) {
            $table->id();

            // Scoping: global/company/offer — scope_id is nullable for global
            $table->enum('scope', ['global','company','offer'])->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();

            // Logical key of the config (e.g., PAYROLL_RULES, VAT_RATES)
            $table->string('key');

            // Versioning
            $table->string('version', 64)->nullable();

            // Content
            $table->json('value_json');

            // Effectivity window
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->boolean('is_active')->default(true);

            // Optional relations
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['scope', 'scope_id', 'key']);
            $table->index(['is_active']);
            $table->index(['effective_from', 'effective_to']);
            $table->unique(['scope', 'scope_id', 'key', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calculator_configs');
    }
};
