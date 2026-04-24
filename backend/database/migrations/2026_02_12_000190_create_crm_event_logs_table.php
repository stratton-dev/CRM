<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained('crm_events')->nullOnDelete();
            $table->string('event_key');
            $table->foreignId('status_id')->nullable()->constrained('crm_statuses')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->index(['event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_event_logs');
    }
};
