<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.google.gemini_key', env('GEMINI_API_KEY', ''));
    }

    public function generateDiagnosis(string $challenge, array $context = []): string
    {
        if (empty($this->apiKey)) {
            Log::warning('Gemini API Key missing');
            return '';
        }

        $industry = $context['industry'] ?? 'B2B';
        $savings = $context['savings'] ? number_format($context['savings'], 0, ',', ' ') . ' PLN' : 'znaczące oszczędności';

        $prompt = "Jesteś ekspertem finansowym i doradą strategicznym dla firm. Klient zgłasza wyzwanie: \"{$challenge}\".
        Branża klienta: {$industry}.
        Potencjalne oszczędności z optymalizacji ZUS/Podatki (Model Eliton Prime): ok. {$savings} rocznie.

        Napisz krótką, 2-3 zdaniową diagnozę (maks 300 znaków), która:
        1. Potwierdza zrozumienie problemu klienta.
        2. Łączy ten problem z brakiem optymalizacji kosztów pracy.
        3. Wskazuje, że wdrożenie modelu (uwolnienie środków) rozwiąże ten problem.

        Styl: Profesjonalny, konkretny, język korzyści. Unikaj ogólników. Nie witaj się, od razu przejdź do diagnozy.";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 150,
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            }

            Log::error('Gemini API Error: ' . $response->body());
            return '';

        } catch (\Exception $e) {
            Log::error('Gemini Service Exception: ' . $e->getMessage());
            return '';
        }
    }
}
