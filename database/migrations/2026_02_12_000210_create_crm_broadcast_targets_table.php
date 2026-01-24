<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_broadcast_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('crm_broadcasts')->cascadeOnDelete();
            $table->enum('target_type', ['ROLE', 'TEAM']);
            $table->string('target_value');
            $table->timestamps();
            $table->unique(['broadcast_id', 'target_type', 'target_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_broadcast_targets');
    }
};
