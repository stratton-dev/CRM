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
        Schema::create('payroll_spreadsheets', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('client_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // File info
            $table->string('original_filename');
            $table->string('storage_path');

            // Status and processing results
            $table->enum('status', ['UPLOADED', 'PROCESSING', 'GENERATED', 'SENT', 'ERROR'])->default('UPLOADED');
            $table->string('result_pdf_path')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_spreadsheets');
    }
};
