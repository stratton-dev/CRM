<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_mail_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('folder_key')->index();
            $table->string('folder_path')->nullable();
            $table->unsignedBigInteger('uid')->index();
            $table->string('message_id')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('to_email')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->json('attachments')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->boolean('read')->default(false)->index();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'folder_key', 'uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_mail_messages');
    }
};
