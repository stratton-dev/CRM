<?php

namespace App\Services\Ai;

use App\Models\AiMemory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MemoryService
{
    // Maks. tokenów pamięci w system prompt (~600 znaków)
    private const MAX_MEMORY_CHARS = 800;
    // Maks. wspomnień per zapytanie
    private const MAX_MEMORIES = 6;
    // Maks. wspomnień per użytkownik (stare są usuwane)
    private const MEMORIES_CAP = 200;

    public function __construct(
        private EmbeddingService $embeddingService,
    ) {}

    /**
     * Pobiera najbardziej pasujące wspomnienia dla danego zapytania.
     * Zwraca gotowy string do wklejenia w system prompt.
     */
    public function retrieveRelevant(User $user, string $query): string
    {
        $memories = $this->findRelevant($user, $query, self::MAX_MEMORIES);

        if (empty($memories)) {
            return '';
        }

        // Zaktualizuj last_accessed_at i access_count
        $ids = array_column($memories, 'id');
        AiMemory::whereIn('id', $ids)->increment('access_count');
        AiMemory::whereIn('id', $ids)->update(['last_accessed_at' => now()]);

        // Formatuj wspomnienia jako czytelną sekcję
        $lines = array_map(function (object $m) {
            $icon = match ($m->memory_type) {
                'client_fact' => '🏢',
                'preference'  => '👤',
                'decision'    => '✅',
                'context'     => '💡',
                default       => '•',
            };
            return "{$icon} {$m->content}";
        }, $memories);

        $text = implode("\n", $lines);

        // Ogranicz długość
        if (mb_strlen($text) > self::MAX_MEMORY_CHARS) {
            $text = mb_substr($text, 0, self::MAX_MEMORY_CHARS) . '...';
        }

        return $text;
    }

    /**
     * Wyodrębnia wspomnienia z ostatnich wiadomości rozmowy i zapisuje je.
     * Wywoływane asynchronicznie (z queue job) po odpowiedzi bota.
     */
    public function extractAndStore(User $user, int $conversationId, array $lastMessages): void
    {
        if (empty($lastMessages)) {
            return;
        }

        $apiKey = config('ai.anthropic_key');
        if (!$apiKey) {
            Log::warning('MemoryService: brak ANTHROPIC_API_KEY, pomijam ekstrakcję.');
            return;
        }

        // Zbuduj mini-kontekst z ostatnich wiadomości
        $dialogue = '';
        foreach (array_slice($lastMessages, -4) as $msg) {
            $role    = $msg['role'] === 'user' ? 'Użytkownik' : 'Asystent';
            $content = mb_substr($msg['content'] ?? '', 0, 500);
            $dialogue .= "{$role}: {$content}\n";
        }

        $extractionPrompt = <<<PROMPT
Przeanalizuj poniższą rozmowę i wyodrębnij 0-3 fakty warte zapamiętania na przyszłość.
Zapisuj TYLKO konkretne, użyteczne fakty — NIE ogólne tematy rozmowy.

Typy faktów:
- client_fact: fakty o konkretnych klientach (budżet, preferencje, daty, decyzje klienta)
- preference: preferencje i nawyki użytkownika (jak pisze maile, styl pracy, co lubi/nie lubi)
- decision: podjęte decyzje i zadania do zrobienia (z terminem jeśli jest)
- context: ważny kontekst osobisty lub firmowy

ROZMOWA:
{$dialogue}

Odpowiedz TYLKO jako JSON array. Jeśli nie ma nic wartego zapamiętania, zwróć [].
Format:
[{"content": "treść faktu po polsku", "memory_type": "typ", "importance": 0.0-1.0}]

Przykłady dobrych faktów:
- "Klient Kowalski z firmy XYZ ma budżet 80k rocznie" (client_fact, 0.9)
- "Tomek woli krótkie maile bez zbędnych formalności" (preference, 0.8)
- "Do wysłania oferta dla ABC do piątku 9 maja" (decision, 0.95)
- "Firma zatrudnia 45 pracowników i przechodzi na UoP" (client_fact, 0.85)

NIE zapisuj:
- Pytań o godzinę, datę
- Ogólnych rozmów o systemie
- Duplikatów istniejących faktów
PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])->timeout(20)->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 512,
                'messages'   => [
                    ['role' => 'user', 'content' => $extractionPrompt],
                ],
            ]);

            if ($response->failed()) {
                Log::warning('MemoryService: błąd API ekstrakcji', ['status' => $response->status()]);
                return;
            }

            $text    = $response->json('content.0.text', '[]');
            // Wyciągnij JSON z odpowiedzi (może być otoczony tekstem)
            preg_match('/\[.*\]/s', $text, $matches);
            $jsonStr  = $matches[0] ?? '[]';
            $memories = json_decode($jsonStr, true);

            if (!is_array($memories) || empty($memories)) {
                return;
            }

            $this->storeMemories($user, $memories, $conversationId);

        } catch (\Exception $e) {
            Log::error('MemoryService::extractAndStore error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Zapisuje wyodrębnione wspomnienia do bazy z embeddingami.
     */
    public function storeMemories(User $user, array $memories, int $conversationId): void
    {
        foreach ($memories as $mem) {
            $content = trim($mem['content'] ?? '');
            $type    = in_array($mem['memory_type'] ?? '', ['client_fact', 'preference', 'decision', 'context'])
                ? $mem['memory_type']
                : 'context';
            $importance = min(1.0, max(0.0, (float) ($mem['importance'] ?? 0.7)));

            if (mb_strlen($content) < 10) continue;

            // Sprawdź czy podobna pamięć już istnieje (unikaj duplikatów)
            if ($this->isSimilarExists($user->id, $content)) {
                continue;
            }

            $memory = AiMemory::create([
                'user_id'                => $user->id,
                'content'                => $content,
                'memory_type'            => $type,
                'importance'             => $importance,
                'source_conversation_id' => $conversationId,
            ]);

            // Generuj embedding na pgsql
            if (config('database.default') === 'pgsql' && config('ai.openai_key')) {
                try {
                    $embedding = $this->embeddingService->embed($content);
                    $vector    = $this->embeddingService->vectorToString($embedding);
                    DB::statement(
                        'UPDATE ai_memories SET embedding = ?::vector WHERE id = ?',
                        [$vector, $memory->id]
                    );
                } catch (\Exception $e) {
                    Log::warning('MemoryService: embedding error', ['error' => $e->getMessage()]);
                }
            }
        }

        // Przytnij stare wspomnienia jeśli przekroczono limit
        $this->pruneOldMemories($user->id);
    }

    /**
     * Ręczne dodanie wspomnienia (np. z UI).
     */
    public function addManual(User $user, string $content, string $type = 'context', float $importance = 0.8): AiMemory
    {
        $memory = AiMemory::create([
            'user_id'     => $user->id,
            'content'     => $content,
            'memory_type' => $type,
            'importance'  => $importance,
        ]);

        if (config('database.default') === 'pgsql' && config('ai.openai_key')) {
            try {
                $embedding = $this->embeddingService->embed($content);
                $vector    = $this->embeddingService->vectorToString($embedding);
                DB::statement(
                    'UPDATE ai_memories SET embedding = ?::vector WHERE id = ?',
                    [$vector, $memory->id]
                );
            } catch (\Exception $e) {
                Log::warning('MemoryService: embedding error', ['error' => $e->getMessage()]);
            }
        }

        return $memory;
    }

    /**
     * Pobiera wszystkie wspomnienia użytkownika (do podglądu w UI).
     */
    public function getAll(User $user, string $type = null): \Illuminate\Support\Collection
    {
        $query = AiMemory::where('user_id', $user->id)
            ->orderByDesc('importance')
            ->orderByDesc('updated_at');

        if ($type) {
            $query->where('memory_type', $type);
        }

        return $query->get();
    }

    /**
     * Usuwa konkretne wspomnienie.
     */
    public function forget(User $user, int $memoryId): bool
    {
        return AiMemory::where('id', $memoryId)
            ->where('user_id', $user->id)
            ->delete() > 0;
    }

    /**
     * Usuwa wszystkie wspomnienia użytkownika.
     */
    public function forgetAll(User $user): int
    {
        return AiMemory::where('user_id', $user->id)->delete();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function findRelevant(User $user, string $query, int $limit): array
    {
        if (config('database.default') === 'pgsql' && config('ai.openai_key')) {
            return $this->findByVector($user, $query, $limit);
        }
        return $this->findByKeyword($user, $query, $limit);
    }

    private function findByVector(User $user, string $query, int $limit): array
    {
        try {
            $embedding = $this->embeddingService->embed($query);
            $vector    = $this->embeddingService->vectorToString($embedding);

            return DB::select(
                "SELECT id, content, memory_type, importance,
                        1 - (embedding <=> :emb::vector) AS similarity
                 FROM ai_memories
                 WHERE user_id = :uid
                   AND embedding IS NOT NULL
                 ORDER BY embedding <=> :emb2::vector
                 LIMIT :lim",
                ['emb' => $vector, 'uid' => $user->id, 'emb2' => $vector, 'lim' => $limit]
            );
        } catch (\Exception $e) {
            Log::warning('MemoryService: vector search error', ['error' => $e->getMessage()]);
            return $this->findByKeyword($user, $query, $limit);
        }
    }

    private function findByKeyword(User $user, string $query, int $limit): array
    {
        $words = array_filter(
            explode(' ', mb_strtolower($query)),
            fn($w) => mb_strlen($w) >= 4
        );

        if (empty($words)) {
            // Zwróć najważniejsze wspomnienia
            return AiMemory::where('user_id', $user->id)
                ->orderByDesc('importance')
                ->orderByDesc('last_accessed_at')
                ->limit($limit)
                ->get(['id', 'content', 'memory_type', 'importance'])
                ->toArray();
        }

        $query = AiMemory::where('user_id', $user->id);
        foreach (array_slice($words, 0, 3) as $word) {
            $query->orWhere('content', 'like', "%{$word}%");
        }

        return $query
            ->orderByDesc('importance')
            ->limit($limit)
            ->get(['id', 'content', 'memory_type', 'importance'])
            ->toArray();
    }

    private function isSimilarExists(int $userId, string $content): bool
    {
        // Prosta heurystyka: sprawdź czy pierwsze 50 znaków już jest w bazie
        $prefix = mb_substr($content, 0, 50);
        return AiMemory::where('user_id', $userId)
            ->where('content', 'like', "%{$prefix}%")
            ->exists();
    }

    private function pruneOldMemories(int $userId): void
    {
        $count = AiMemory::where('user_id', $userId)->count();
        if ($count > self::MEMORIES_CAP) {
            // Usuń najstarsze i najmniej ważne
            $toDelete = $count - self::MEMORIES_CAP;
            $ids      = AiMemory::where('user_id', $userId)
                ->orderBy('importance')
                ->orderBy('last_accessed_at')
                ->limit($toDelete)
                ->pluck('id');
            AiMemory::whereIn('id', $ids)->delete();
        }
    }
}
