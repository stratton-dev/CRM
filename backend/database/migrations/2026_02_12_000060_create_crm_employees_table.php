<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('pesel')->nullable();
            $table->enum('contract_type', ['UoP', 'UZ'])->default('UoP');
            $table->decimal('benefit_amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_employees');
    }
};
