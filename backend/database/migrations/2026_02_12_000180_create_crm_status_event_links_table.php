<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_status_event_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_id')->constrained('crm_statuses')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('crm_events')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['status_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_status_event_links');
    }
};
