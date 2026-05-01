<?php

namespace App\Jobs;

use App\Models\KnowledgeBaseChunk;
use App\Models\KnowledgeBaseDocument;
use App\Services\Ai\EmbeddingService;
use App\Services\Ai\PdfProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessKnowledgeBaseDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 2;

    public function __construct(
        public readonly int    $documentId,
        public readonly string $filePath,
    ) {}

    public function handle(PdfProcessingService $pdfService, EmbeddingService $embeddingService): void
    {
        $doc = KnowledgeBaseDocument::findOrFail($this->documentId);
        $doc->update(['status' => 'processing']);

        try {
            $text = $pdfService->extractText($this->filePath);
            if (empty(trim($text))) {
                throw new \RuntimeException('PDF nie zawiera tekstu (może być skanowanym obrazem).');
            }

            $chunks = $pdfService->chunkText($text);
            if (empty($chunks)) {
                throw new \RuntimeException('Nie udało się podzielić tekstu na chunki.');
            }

            $contents   = array_column($chunks, 'content');
            $embeddings = config('ai.openai_key') && config('database.default') === 'pgsql'
                ? $embeddingService->embedBatch($contents)
                : array_fill(0, count($contents), null);

            DB::transaction(function () use ($doc, $chunks, $embeddings, $pdfService, $embeddingService) {
                $doc->chunks()->delete();

                foreach ($chunks as $i => $chunk) {
                    $chunkData = [
                        'document_id'  => $doc->id,
                        'content'      => $chunk['content'],
                        'chunk_index'  => $chunk['index'],
                        'tokens_count' => $pdfService->estimateTokens($chunk['content']),
                    ];

                    $saved = KnowledgeBaseChunk::create($chunkData);

                    if (config('database.default') === 'pgsql' && isset($embeddings[$i]) && $embeddings[$i] !== null) {
                        $vectorStr = $embeddingService->vectorToString($embeddings[$i]);
                        DB::statement(
                            "UPDATE knowledge_base_chunks SET embedding = ?::vector WHERE id = ?",
                            [$vectorStr, $saved->id]
                        );
                    }
                }
            });

            if (file_exists($this->filePath)) {
                @unlink($this->filePath);
            }

            $doc->update([
                'status'       => 'ready',
                'chunks_count' => count($chunks),
            ]);

            Log::info("KnowledgeBase: dokument #{$doc->id} przetworzony, " . count($chunks) . " chunków.");

        } catch (\Exception $e) {
            if (file_exists($this->filePath)) {
                @unlink($this->filePath);
            }
            $doc->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            Log::error("KnowledgeBase: błąd przetwarzania #{$doc->id}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
