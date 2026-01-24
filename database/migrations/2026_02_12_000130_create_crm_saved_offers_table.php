<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_saved_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->integer('employees_uop')->default(0);
            $table->decimal('avg_wage_uop', 12, 2)->default(0);
            $table->integer('employees_uz')->default(0);
            $table->decimal('estimated_savings', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_saved_offers');
    }
};
