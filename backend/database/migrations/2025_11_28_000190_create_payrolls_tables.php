<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('companies');
            $table->string('month');
            $table->boolean('imported')->default(false);
            $table->timestamps();
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->enum('employment_type', ['UOP', 'UZ']);
            $table->integer('salary_gross');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payrolls');
    }
};
