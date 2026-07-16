<?php

namespace App\Services\Ai;

use Smalot\PdfParser\Parser;

class PdfProcessingService
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    public function extractText(string $filePath): string
    {
        try {
            $pdf  = $this->parser->parseFile($filePath);
            $text = $pdf->getText();
            return $this->cleanText($text);
        } catch (\Exception $e) {
            throw new \RuntimeException("Nie udało się przetworzyć PDF: " . $e->getMessage());
        }
    }

    public function chunkText(string $text): array
    {
        $chunkSize = config('ai.chunk_size', 2000);
        $overlap   = config('ai.chunk_overlap', 400);
        $chunks    = [];
        $textLen   = mb_strlen($text);
        $index     = 0;
        $position  = 0;

        while ($position < $textLen) {
            $end   = min($position + $chunkSize, $textLen);
            $chunk = mb_substr($text, $position, $end - $position);

            if ($end < $textLen) {
                $lastBreak = mb_strrpos($chunk, "\n\n");
                if ($lastBreak === false || $lastBreak < $chunkSize * 0.5) {
                    $lastBreak = mb_strrpos($chunk, "\n");
                }
                if ($lastBreak === false || $lastBreak < $chunkSize * 0.5) {
                    $lastBreak = mb_strrpos($chunk, '. ');
                }
                if ($lastBreak !== false && $lastBreak > $chunkSize * 0.3) {
                    $chunk = mb_substr($chunk, 0, $lastBreak + 1);
                }
            }

            $trimmed = trim($chunk);
            if (mb_strlen($trimmed) > 50) {
                $chunks[] = [
                    'content' => $trimmed,
                    'index'   => $index++,
                ];
            }

            $prev = $position;
            $position += mb_strlen($chunk) - $overlap;
            // Gwarancja postępu: gdy ogon tekstu ma długość <= overlap, przyrost
            // bywa 0/ujemny (np. textLen=5000/chunk=2000/overlap=400 → utyka na 4600)
            // → pętla nieskończona i OOM joba KB. Guard `<= 0` tego NIE łapie (pozycja
            // dodatnia). Przy braku postępu skaczemy na koniec chunku — a gdy to koniec
            // tekstu, `while` się kończy.
            if ($position <= $prev) {
                $position = $end;
            }
        }

        return $chunks;
    }

    public function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = preg_replace('/^\s+|\s+$/m', '', $text);
        return trim($text);
    }
}
