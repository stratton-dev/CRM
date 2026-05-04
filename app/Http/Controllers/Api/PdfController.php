<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfController extends Controller
{
    public function generateOffer(Request $request): StreamedResponse|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
    {
        $request->validate([
            'type' => 'required|in:short,long,product-card',
            'data' => 'required|array',
        ]);

        $type = $request->input('type');
        $data = $request->input('data');

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpFile = $tmpDir . '/offer_' . Str::uuid() . '.json';
        file_put_contents($tmpFile, json_encode($data, JSON_UNESCAPED_UNICODE));

        $scriptPath = base_path('scripts/pdf-generator.mjs');
        $cmd = 'node ' . escapeshellarg($scriptPath)
            . ' --type ' . escapeshellarg($type)
            . ' --data-file ' . escapeshellarg($tmpFile)
            . ' 2>&1';

        $output = shell_exec($cmd);

        @unlink($tmpFile);

        // Detect puppeteer unavailable → return HTML for window.print() fallback
        if ($output && str_contains($output, 'PUPPETEER_UNAVAILABLE:')) {
            $htmlContent = substr($output, strpos($output, 'PUPPETEER_UNAVAILABLE:') + strlen('PUPPETEER_UNAVAILABLE:'));
            return response($htmlContent, 501)
                ->header('Content-Type', 'text/html; charset=utf-8')
                ->header('X-Pdf-Fallback', 'true');
        }

        if (!$output || strlen(trim($output)) < 100) {
            return response()->json([
                'error' => 'PDF generation failed',
                'details' => $output,
            ], 500);
        }

        $pdfBase64 = trim($output);

        // Validate base64
        if (!preg_match('/^[A-Za-z0-9+\/]+=*$/', $pdfBase64)) {
            return response()->json([
                'error' => 'Invalid PDF output from Node.js',
                'details' => substr($output, 0, 500),
            ], 500);
        }

        $pdf = base64_decode($pdfBase64);

        $filename = match($type) {
            'short'        => 'oferta-krotka-stratton.pdf',
            'long'         => 'oferta-pelna-stratton.pdf',
            'product-card' => 'karta-produktu-eliton-prime.pdf',
            default        => 'oferta-stratton.pdf',
        };

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
