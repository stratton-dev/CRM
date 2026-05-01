<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessKnowledgeBaseDocument;
use App\Models\KnowledgeBaseDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

        $doc = KnowledgeBaseDocument::create([
            'title'             => $request->input('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'original_filename' => $file->getClientOriginalName(),
            'mime_type'         => $file->getMimeType(),
            'file_size'         => $file->getSize(),
            'status'            => 'pending',
            'uploaded_by'       => $user->id,
        ]);

        $tmpPath = sys_get_temp_dir() . '/kb_' . Str::uuid() . '.pdf';
        $file->move(dirname($tmpPath), basename($tmpPath));

        ProcessKnowledgeBaseDocument::dispatch($doc->id, $tmpPath);

        Log::info("KnowledgeBase: upload dokumentu #{$doc->id} przez user #{$user->id}");

        return response()->json(['data' => $doc, 'message' => 'Dokument przyjęty do przetworzenia.'], 201);
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
