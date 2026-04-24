<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->integer('employee_count');
            $table->integer('savings_amount');
            $table->date('valid_until');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculations');
    }
};
