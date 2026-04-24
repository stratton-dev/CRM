<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Ai\GeminiService;

class AiController extends Controller
{
    private GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    public function generateDiagnosis(Request $request)
    {
        $validated = $request->validate([
            'challenge' => 'required|string|min:3',
            'context' => 'nullable|array',
        ]);

        $challenge = $validated['challenge'];
        $context = $validated['context'] ?? [];

        // Try getting AI response
        $diagnosis = $this->geminiService->generateDiagnosis($challenge, $context);

        // If AI fails (e.g. no key), return empty string so frontend can fallback
        // Or return a fallback here? No, let frontend handle fallback for now.
        if (empty($diagnosis)) {
            // Optional: Implement simple fallback here if PHP service fails
            return response()->json([
                'diagnosis' => null,
                'message' => 'AI service unavailable, using local heuristics.'
            ]);
        }

        return response()->json([
            'diagnosis' => $diagnosis,
            'source' => 'gemini-flash-1.5'
        ]);
    }
}
