<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->string('target_id')->nullable();
            $table->text('details');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_audit_logs');
    }
};
