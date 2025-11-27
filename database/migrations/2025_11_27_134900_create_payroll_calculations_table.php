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
        Schema::create('payroll_calculations', function (Blueprint $table) {
            $table->id();

            // Relations (nullable to allow standalone calculations)
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained('offers')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();

            // Period (for payroll)
            $table->unsignedSmallInteger('period_year')->nullable();
            $table->unsignedTinyInteger('period_month')->nullable(); // 1..12

            // Engine/config info
            $table->string('engine_version', 32)->nullable();
            $table->string('config_version', 64)->nullable();
            $table->enum('source', ['manual','import','api','ui'])->default('ui');

            // Data
            $table->json('inputs_json');
            $table->json('outputs_json')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Helpful indexes
            $table->index(['company_id', 'employee_id']);
            $table->index(['period_year', 'period_month']);
            $table->index(['offer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_calculations');
    }
};
