<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessKnowledgeBaseDocument;
use App\Models\KnowledgeBaseDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KnowledgeBaseController extends Controller
{
    public function index(): JsonResponse
    {
        $docs = KnowledgeBaseDocument::with('uploader:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $docs]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file'  => 'required|file|mimes:pdf|max:20480',
                'title' => 'nullable|string|max:255',
            ]);

            $user = Auth::user();
            $file = $request->file('file');

            if (!$file || !$file->isValid()) {
                return response()->json(['message' => 'Plik PDF jest nieprawidłowy lub nie został przesłany.'], 422);
            }

            // Kopiuj do storage zamiast używać tymczasowego pliku
            $storagePath  = 'kb-uploads/' . Str::uuid() . '.pdf';
            $fileContents = file_get_contents($file->getRealPath());
            if ($fileContents === false) {
                return response()->json(['message' => 'Nie udało się odczytać przesłanego pliku.'], 422);
            }

            Storage::disk('local')->put($storagePath, $fileContents);
            unset($fileContents); // zwolnij pamięć
            $absolutePath = Storage::disk('local')->path($storagePath);

            $doc = KnowledgeBaseDocument::create([
                'title'             => $request->input('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'original_filename' => $file->getClientOriginalName(),
                'mime_type'         => $file->getMimeType() ?? 'application/pdf',
                'file_size'         => $file->getSize(),
                'status'            => 'pending',
                'uploaded_by'       => $user->id,
            ]);

            try {
                ProcessKnowledgeBaseDocument::dispatch($doc->id, $absolutePath);
            } catch (\Throwable $e) {
                Log::error("KnowledgeBase: dispatch/processing error #{$doc->id}: " . get_class($e) . ': ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $doc->update([
                    'status'        => 'failed',
                    'error_message' => get_class($e) . ': ' . $e->getMessage(),
                ]);
            }

            $doc->refresh();
            Log::info("KnowledgeBase: upload #{$doc->id} przez user #{$user->id}, status: {$doc->status}");

            if ($doc->status === 'failed') {
                return response()->json(['message' => 'Błąd przetwarzania PDF: ' . $doc->error_message], 422);
            }

            return response()->json(['data' => $doc, 'message' => 'Dokument przetworzony pomyślnie.'], 201);

        } catch (\Throwable $e) {
            Log::error('KnowledgeBase store() fatal: ' . get_class($e) . ': ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Błąd serwera: ' . get_class($e) . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $doc = KnowledgeBaseDocument::findOrFail($id);
        $doc->chunks()->delete();
        $doc->delete();

        return response()->json(['message' => 'Dokument usunięty z bazy wiedzy.']);
    }

    public function status(int $id): JsonResponse
    {
        $doc = KnowledgeBaseDocument::findOrFail($id);
        return response()->json(['data' => [
            'id'           => $doc->id,
            'status'       => $doc->status,
            'chunks_count' => $doc->chunks_count,
            'error_message'=> $doc->error_message,
        ]]);
    }
}
