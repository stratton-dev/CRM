<?php

namespace App\Jobs;

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Ai\MemoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExtractMemoriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries   = 2;
    public int $backoff = 10;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $userId,
    ) {}

    public function handle(MemoryService $memoryService): void
    {
        $user = User::find($this->userId);
        $conv = AiConversation::find($this->conversationId);

        if (!$user || !$conv) {
            return;
        }

        // Pobierz ostatnie 4 wiadomości (2 pary user+assistant)
        $messages = $conv->messages()
            ->orderByDesc('id')
            ->limit(4)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        if (empty($messages)) {
            return;
        }

        $memoryService->extractAndStore($user, $this->conversationId, $messages);

        Log::debug("ExtractMemoriesJob: przetworzono konwersację #{$this->conversationId}");
    }

    public function failed(\Throwable $e): void
    {
        Log::warning("ExtractMemoriesJob failed #{$this->conversationId}", ['error' => $e->getMessage()]);
    }
}
