<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('from_name');
            $table->string('from_email');
            $table->string('to_email');
            $table->string('subject');
            $table->longText('body');
            $table->enum('folder', ['INBOX', 'SENT', 'TRASH'])->default('INBOX');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_emails');
    }
};
