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
        Schema::create('historical_payroll_calculations', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('client_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('original_spreadsheet_id')->nullable()->constrained('payroll_spreadsheets')->nullOnDelete();

            // File info
            $table->string('original_filename');
            $table->string('result_filename')->nullable();

            // Paths
            $table->string('source_file_path')->nullable();
            $table->string('result_pdf_path')->nullable();

            // Meta
            $table->timestamp('calculated_at')->useCurrent();
            $table->json('calculation_meta')->nullable(); // Store savings amount etc.

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historical_payroll_calculations');
    }
};
