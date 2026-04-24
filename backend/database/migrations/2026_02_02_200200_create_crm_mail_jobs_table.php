<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_mail_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type');
            $table->json('payload');
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('locked_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_mail_jobs');
    }
};
