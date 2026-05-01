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
    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int    $documentId,
        public readonly string $filePath,
    ) {}

    public function failed(?\Throwable $exception): void
    {
        $doc = KnowledgeBaseDocument::find($this->documentId);
        if ($doc && $doc->status !== 'ready') {
            $doc->update([
                'status'        => 'failed',
                'error_message' => $exception ? get_class($exception) . ': ' . $exception->getMessage() : 'Job failed after max attempts',
            ]);
        }
        if (file_exists($this->filePath)) {
            @unlink($this->filePath);
        }
    }

    public function handle(PdfProcessingService $pdfService, EmbeddingService $embeddingService): void
    {
        ini_set('memory_limit', '512M');

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
                        try {
                            $vectorStr = $embeddingService->vectorToString($embeddings[$i]);
                            DB::statement(
                                "UPDATE knowledge_base_chunks SET embedding = ?::vector WHERE id = ?",
                                [$vectorStr, $saved->id]
                            );
                        } catch (\Exception $vectorEx) {
                            Log::warning("KnowledgeBase: nie udało się zapisać embeddingu dla chunka #{$saved->id}: " . $vectorEx->getMessage());
                        }
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

        } catch (\Throwable $e) {
            if (file_exists($this->filePath)) {
                @unlink($this->filePath);
            }
            $doc->update([
                'status'        => 'failed',
                'error_message' => get_class($e) . ': ' . $e->getMessage(),
            ]);
            Log::error("KnowledgeBase: błąd przetwarzania #{$doc->id}", [
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
        }
    }
}
