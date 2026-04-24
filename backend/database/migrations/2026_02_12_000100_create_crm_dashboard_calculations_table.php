<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_dashboard_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('company');
            $table->string('nip')->nullable();
            $table->string('meeting_id')->nullable();
            $table->date('calculation_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_dashboard_calculations');
    }
};
