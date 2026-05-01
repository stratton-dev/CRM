<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('knowledge_base_documents')) {
            Schema::create('knowledge_base_documents', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('original_filename');
                $table->string('mime_type')->default('application/pdf');
                $table->integer('file_size')->default(0);
                $table->enum('status', ['pending', 'processing', 'ready', 'failed'])->default('pending');
                $table->integer('chunks_count')->default(0);
                $table->text('error_message')->nullable();
                $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('knowledge_base_chunks')) {
            Schema::create('knowledge_base_chunks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')
                      ->references('id')->on('knowledge_base_documents')->cascadeOnDelete();
                $table->longText('content');
                $table->integer('chunk_index')->default(0);
                $table->integer('tokens_count')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });

            // Dodaj kolumnę vector tylko jeśli pgvector jest dostępny
            if (config('database.default') === 'pgsql') {
                try {
                    DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
                    DB::statement('ALTER TABLE knowledge_base_chunks ADD COLUMN IF NOT EXISTS embedding vector(1536)');
                    DB::statement('CREATE INDEX IF NOT EXISTS knowledge_base_chunks_embedding_idx ON knowledge_base_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
                } catch (\Exception $e) {
                    // pgvector niedostępny — embeddingi będą pomijane
                    \Illuminate\Support\Facades\Log::warning('pgvector unavailable, skipping embedding column: ' . $e->getMessage());
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_chunks');
        Schema::dropIfExists('knowledge_base_documents');
    }
};
