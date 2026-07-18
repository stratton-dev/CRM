<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_mail_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('imap_host');
            $table->unsignedInteger('imap_port')->default(993);
            $table->boolean('imap_secure')->default(true);
            $table->string('imap_username');
            $table->text('imap_password');
            $table->string('imap_inbox_folder')->default('INBOX');
            $table->string('imap_sent_folder')->default('Sent');
            $table->string('imap_trash_folder')->default('Trash');
            $table->string('smtp_host');
            $table->unsignedInteger('smtp_port')->default(465);
            $table->boolean('smtp_secure')->default(true);
            $table->string('smtp_username');
            $table->text('smtp_password');
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_mail_configs');
    }
};
