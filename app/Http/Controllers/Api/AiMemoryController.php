<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\MemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiMemoryController extends Controller
{
    public function __construct(
        private MemoryService $memoryService,
    ) {}

    /**
     * GET /v1/ai-memory
     * Lista wszystkich wspomnień zalogowanego użytkownika.
     */
    public function index(Request $request): JsonResponse
    {
        $user     = Auth::user();
        $type     = $request->query('type');
        $memories = $this->memoryService->getAll($user, $type);

        return response()->json([
            'data'  => $memories,
            'count' => $memories->count(),
        ]);
    }

    /**
     * POST /v1/ai-memory
     * Ręczne dodanie wspomnienia.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'content'     => 'required|string|min:5|max:500',
            'memory_type' => 'nullable|in:client_fact,preference,decision,context',
            'importance'  => 'nullable|numeric|min:0|max:1',
        ]);

        $user   = Auth::user();
        $memory = $this->memoryService->addManual(
            $user,
            $request->input('content'),
            $request->input('memory_type', 'context'),
            (float) $request->input('importance', 0.8),
        );

        return response()->json(['data' => $memory], 201);
    }

    /**
     * DELETE /v1/ai-memory/{id}
     * Usuń konkretne wspomnienie.
     */
    public function destroy(int $id): JsonResponse
    {
        $user    = Auth::user();
        $deleted = $this->memoryService->forget($user, $id);

        if (!$deleted) {
            return response()->json(['error' => 'Wspomnienie nie znalezione.'], 404);
        }

        return response()->json(['message' => 'Wspomnienie usunięte.']);
    }

    /**
     * DELETE /v1/ai-memory
     * Wyczyść całą pamięć (reset).
     */
    public function destroyAll(): JsonResponse
    {
        $user  = Auth::user();
        $count = $this->memoryService->forgetAll($user);

        return response()->json(['message' => "Usunięto {$count} wspomnień.", 'deleted' => $count]);
    }
}
