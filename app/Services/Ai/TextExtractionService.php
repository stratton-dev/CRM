<?php

namespace App\Services\Ai;

use PhpOffice\PhpWord\IOFactory as WordFactory;
use Smalot\PdfParser\Parser as PdfParser;

class TextExtractionService
{
    private const MAX_CHARS = 50000;

    private PdfParser $pdfParser;

    public function __construct()
    {
        $this->pdfParser = new PdfParser();
    }

    public function extract(string $filePath, string $mimeType): string
    {
        $text = match(true) {
            $mimeType === 'application/pdf'                                                                     => $this->extractPdf($filePath),
            in_array($mimeType, ['text/plain', 'text/markdown', 'text/x-markdown'])                           => $this->extractText($filePath),
            in_array($mimeType, ['application/msword',
                                  'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])  => $this->extractDocx($filePath),
            default => throw new \RuntimeException(
                'Nieobsługiwany format pliku. Obsługiwane: PDF, DOC, DOCX, TXT, MD'
            ),
        };

        $text = $this->clean($text);

        if (mb_strlen($text) > self::MAX_CHARS) {
            $total = mb_strlen($text);
            $text  = mb_substr($text, 0, self::MAX_CHARS);
            $text .= "\n\n[OBCIĘTO — plik ma łącznie {$total} znaków]";
        }

        return $text;
    }

    private function extractPdf(string $path): string
    {
        $pdf = $this->pdfParser->parseFile($path);
        return $pdf->getText();
    }

    private function extractText(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Nie można odczytać pliku: {$path}");
        }
        return $content;
    }

    private function extractDocx(string $path): string
    {
        $phpWord = WordFactory::load($path);
        $text    = '';
        foreach ($phpWord->getSections() as $section) {
            $text .= $this->extractElements($section->getElements());
        }
        return $text;
    }

    private function extractElements(array $elements): string
    {
        $text = '';
        foreach ($elements as $element) {
            if (method_exists($element, 'getText')) {
                $text .= $element->getText() . ' ';
            } elseif (method_exists($element, 'getElements')) {
                $text .= $this->extractElements($element->getElements());
            }
            if (method_exists($element, 'getElements') || method_exists($element, 'getText')) {
                $text .= "\n";
            }
        }
        return $text;
    }

    private function clean(string $text): string
    {
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
