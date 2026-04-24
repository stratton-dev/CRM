<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autenti_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_keycloak_id')->nullable();
            $table->string('initiator_keycloak_id')->nullable();
            $table->string('recipient_name');
            $table->string('recipient_email');
            $table->string('document_list');
            $table->string('status')->default('SENT');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('autenti_process_id')->nullable();
            $table->string('autenti_status')->nullable();
            $table->timestamp('autenti_last_event_at')->nullable();
            $table->timestamps();

            $table->index('autenti_process_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autenti_documents');
    }
};
