<?php

namespace App\Console\Commands;

use App\Jobs\ProcessKnowledgeBaseDocument;
use App\Models\KnowledgeBaseDocument;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportKnowledgeBase extends Command
{
    protected $signature   = 'kb:import {path : Ścieżka do folderu lub pliku PDF} {--user= : ID użytkownika (domyślnie 1)} {--force : Nadpisz istniejące}';
    protected $description = 'Importuj pliki PDF bezpośrednio do bazy wiedzy AI';

    public function handle(): int
    {
        $path   = $this->argument('path');
        $userId = (int) ($this->option('user') ?? 1);
        $force  = $this->option('force');

        ini_set('memory_limit', '512M');

        if (!file_exists($path)) {
            $this->error("Ścieżka nie istnieje: $path");
            return 1;
        }

        $files = is_dir($path)
            ? glob(rtrim($path, '/') . '/*.pdf')
            : [$path];

        if (empty($files)) {
            $this->warn("Brak plików PDF w: $path");
            return 0;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error("Użytkownik ID=$userId nie istnieje.");
            return 1;
        }

        $this->info("Importuję " . count($files) . " plik(ów) jako user: {$user->name} (#{$userId})");

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $title    = pathinfo($filename, PATHINFO_FILENAME);

            if (!$force) {
                $exists = KnowledgeBaseDocument::where('original_filename', $filename)->first();
                if ($exists) {
                    $this->warn("  POMIJAM (już istnieje): $filename [status: {$exists->status}]");
                    continue;
                }
            }

            $this->info("  Przetwarzam: $filename (" . round(filesize($filePath) / 1024) . " KB)");

            // Kopiuj do storage
            $storagePath  = 'kb-uploads/' . Str::uuid() . '.pdf';
            $fileContents = file_get_contents($filePath);
            if ($fileContents === false) {
                $this->error("    Nie udało się odczytać pliku: $filePath");
                continue;
            }

            Storage::disk('local')->put($storagePath, $fileContents);
            unset($fileContents);
            $absolutePath = Storage::disk('local')->path($storagePath);

            $doc = KnowledgeBaseDocument::create([
                'title'             => $title,
                'original_filename' => $filename,
                'mime_type'         => 'application/pdf',
                'file_size'         => filesize($filePath),
                'status'            => 'pending',
                'uploaded_by'       => $userId,
            ]);

            try {
                ProcessKnowledgeBaseDocument::dispatchSync($doc->id, $absolutePath);
                $doc->refresh();
                if ($doc->status === 'ready') {
                    $this->info("    OK: {$doc->chunks_count} chunków, status: ready");
                } else {
                    $this->warn("    UWAGA status: {$doc->status} — {$doc->error_message}");
                }
            } catch (\Throwable $e) {
                $this->error("    BŁĄD: " . get_class($e) . ': ' . $e->getMessage());
                $doc->update([
                    'status'        => 'failed',
                    'error_message' => get_class($e) . ': ' . $e->getMessage(),
                ]);
                Log::error("kb:import błąd dla $filename", ['error' => $e->getMessage()]);
            }
        }

        $this->info("Gotowe.");
        return 0;
    }
}
