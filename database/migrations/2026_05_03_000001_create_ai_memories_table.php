<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->enum('memory_type', ['client_fact', 'preference', 'decision', 'context'])->default('context');
            $table->unsignedBigInteger('related_client_id')->nullable()->index();
            $table->float('importance')->default(0.7);
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->foreignId('source_conversation_id')
                  ->nullable()
                  ->constrained('ai_conversations')
                  ->nullOnDelete();
            $table->timestamps();
        });

        // Kolumna vector tylko na PostgreSQL (Supabase)
        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE ai_memories ADD COLUMN IF NOT EXISTS embedding vector(1536)');
            DB::statement('
                CREATE INDEX IF NOT EXISTS ai_memories_embedding_idx
                ON ai_memories USING ivfflat (embedding vector_cosine_ops)
                WITH (lists = 100)
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_memories');
    }
};
