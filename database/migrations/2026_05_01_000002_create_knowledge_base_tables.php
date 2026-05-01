<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        // Dodaj kolumnę vector tylko na PostgreSQL
        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE knowledge_base_chunks ADD COLUMN embedding vector(1536)');
            DB::statement('CREATE INDEX knowledge_base_chunks_embedding_idx ON knowledge_base_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_chunks');
        Schema::dropIfExists('knowledge_base_documents');
    }
};
