<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('token', 512);          // FCM token or Web Push endpoint
            $table->string('type', 20)->default('web'); // 'web' | 'fcm'
            $table->string('platform', 20)->nullable(); // 'android' | 'ios' | 'web'
            // Web Push subscription fields (only for type='web')
            $table->string('web_endpoint', 1024)->nullable();
            $table->string('web_p256dh', 256)->nullable();
            $table->string('web_auth', 64)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'token']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tokens');
    }
};
