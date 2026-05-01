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
        $request->validate([
            'file'  => 'required|file|mimes:pdf|max:20480',
            'title' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $file = $request->file('file');

        $storagePath = 'kb-uploads/' . Str::uuid() . '.pdf';
        Storage::disk('local')->put($storagePath, file_get_contents($file->getRealPath()));
        $absolutePath = Storage::disk('local')->path($storagePath);

        $doc = KnowledgeBaseDocument::create([
            'title'             => $request->input('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'original_filename' => $file->getClientOriginalName(),
            'mime_type'         => $file->getMimeType(),
            'file_size'         => $file->getSize(),
            'status'            => 'pending',
            'uploaded_by'       => $user->id,
        ]);

        try {
            ProcessKnowledgeBaseDocument::dispatch($doc->id, $absolutePath);
        } catch (\Throwable $e) {
            Log::warning("KnowledgeBase: dispatch error #{$doc->id}: " . $e->getMessage());
        }

        $doc->refresh();
        Log::info("KnowledgeBase: upload dokumentu #{$doc->id} przez user #{$user->id}, status: {$doc->status}");

        if ($doc->status === 'failed') {
            return response()->json(['message' => 'Błąd przetwarzania PDF: ' . $doc->error_message], 422);
        }

        return response()->json(['data' => $doc, 'message' => 'Dokument przetworzony pomyślnie.'], 201);
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
