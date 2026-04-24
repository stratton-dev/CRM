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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('company_id')->constrained('companies')->cascadeOnUpdate()->restrictOnDelete();

            // Basic identity
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Personal data
            $table->date('birth_date')->nullable();
            $table->unsignedTinyInteger('age')->nullable(); // optional cache from import
            $table->enum('gender', ['M','K','O'])->nullable(); // O = other/unspecified

            // Employment / payroll related fields (to support calc imports)
            $table->enum('contract_type', ['ETAT','ZLECENIE','B2B','DZIELO'])->default('ETAT');
            $table->string('zus_type', 20)->default('full'); // e.g. full, student, none
            $table->integer('kup')->nullable(); // Koszty uzyskania przychodu (PLN)
            $table->decimal('kup_percent', 5, 4)->nullable(); // e.g. 0.2
            $table->integer('tax_free_amount')->nullable(); // Ulga podatkowa (PLN)
            $table->boolean('kzp')->default(false); // Kasa Zapomogowo-Pożyczkowa

            // Last imported figures (optional)
            $table->decimal('net_total', 12, 2)->nullable();
            $table->decimal('net_cash', 12, 2)->nullable();

            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'last_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
