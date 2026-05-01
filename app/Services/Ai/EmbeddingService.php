<?php

namespace App\Services\Ai;

use OpenAI\Client;
use OpenAI;

class EmbeddingService
{
    private ?Client $client = null;

    public function __construct()
    {
        $key = config('ai.openai_key');
        if ($key) {
            $this->client = OpenAI::client($key);
        }
    }

    public function embed(string $text): array
    {
        if (!$this->client) return [];

        $response = $this->client->embeddings()->create([
            'model'      => config('ai.embedding_model', 'text-embedding-3-small'),
            'input'      => $this->prepareText($text),
            'dimensions' => config('ai.embedding_dimensions', 1536),
        ]);

        return $response->embeddings[0]->embedding;
    }

    public function embedBatch(array $texts): array
    {
        if (!$this->client || empty($texts)) return [];

        $prepared = array_map(fn($t) => $this->prepareText($t), $texts);

        $response = $this->client->embeddings()->create([
            'model'      => config('ai.embedding_model', 'text-embedding-3-small'),
            'input'      => $prepared,
            'dimensions' => config('ai.embedding_dimensions', 1536),
        ]);

        $embeddings = [];
        foreach ($response->embeddings as $emb) {
            $embeddings[$emb->index] = $emb->embedding;
        }
        ksort($embeddings);
        return array_values($embeddings);
    }

    public function vectorToString(array $embedding): string
    {
        return '[' . implode(',', $embedding) . ']';
    }

    private function prepareText(string $text): string
    {
        return mb_substr(trim($text), 0, 30000);
    }
}
