<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('Nowa rozmowa');
            $table->string('model')->default('claude-sonnet-4-6');
            $table->integer('total_tokens')->default(0);
            $table->timestamps();
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                  ->references('id')->on('ai_conversations')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'tool']);
            $table->longText('content')->nullable();
            $table->json('tool_calls')->nullable();
            $table->integer('tokens_used')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
