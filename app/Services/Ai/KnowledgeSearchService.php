<?php

namespace App\Services\Ai;

use App\Models\KnowledgeBaseChunk;
use Illuminate\Support\Facades\DB;

class KnowledgeSearchService
{
    public function __construct(
        private EmbeddingService $embeddingService
    ) {}

    public function search(string $query, int $limit = null): array
    {
        $limit = $limit ?? config('ai.kb_top_k', 5);

        if (config('database.default') === 'pgsql') {
            try {
                $queryEmbedding = $this->embeddingService->embed($query);
                if (!empty($queryEmbedding)) {
                    $vectorStr = $this->embeddingService->vectorToString($queryEmbedding);

                    $results = DB::select(
                        "SELECT kbc.content,
                                1 - (kbc.embedding <=> :embedding::vector) AS similarity,
                                kbd.title AS document_title
                         FROM knowledge_base_chunks kbc
                         JOIN knowledge_base_documents kbd ON kbd.id = kbc.document_id
                         WHERE kbd.status = 'ready'
                           AND kbc.embedding IS NOT NULL
                         ORDER BY kbc.embedding <=> :embedding2::vector
                         LIMIT :limit",
                        [
                            'embedding'  => $vectorStr,
                            'embedding2' => $vectorStr,
                            'limit'      => $limit,
                        ]
                    );

                    if (!empty($results)) {
                        return array_map(function ($row) {
                            return "[Dokument: {$row->document_title}]\n{$row->content}";
                        }, $results);
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('KnowledgeSearchService: vector search failed, using text fallback', ['error' => $e->getMessage()]);
            }
        }

        return $this->fallbackSearch($query, $limit);
    }

    public function hasDocuments(): bool
    {
        return \App\Models\KnowledgeBaseDocument::where('status', 'ready')->exists();
    }

    private function fallbackSearch(string $query, int $limit): array
    {
        $words   = array_filter(explode(' ', mb_strtolower($query)));
        $results = [];
        $likeOp  = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        foreach ($words as $word) {
            if (mb_strlen($word) < 3) continue;
            $chunks = KnowledgeBaseChunk::with('document:id,title')
                ->whereHas('document', fn($q) => $q->where('status', 'ready'))
                ->where('content', $likeOp, "%{$word}%")
                ->limit($limit)
                ->get();
            foreach ($chunks as $chunk) {
                $title = $chunk->document->title ?? 'Dokument';
                $results[$chunk->id] = "[Dokument: {$title}]\n{$chunk->content}";
            }
            if (count($results) >= $limit) break;
        }

        return array_slice(array_values($results), 0, $limit);
    }
}
