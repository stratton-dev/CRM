<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_counters', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->unique();
            $table->unsignedInteger('next_number');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_counters');
    }
};
