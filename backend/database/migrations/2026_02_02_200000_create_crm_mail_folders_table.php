<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_mail_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('path');
            $table->string('name')->nullable();
            $table->json('flags')->nullable();
            $table->boolean('listed')->default(true);
            $table->string('special_use')->nullable();
            $table->unsignedBigInteger('uid_validity')->nullable();
            $table->unsignedBigInteger('uid_next')->nullable();
            $table->unsignedInteger('exists')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_mail_folders');
    }
};
