<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmKnowledgeFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CrmKnowledgeFilesController extends Controller
{
    public function index(Request $request)
    {
        $q = CrmKnowledgeFile::query();
        if ($category = $request->string('category')->toString()) {
            $q->where('category', $category);
        }
        if ($search = $request->string('search')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%");
            });
        }
        return $q->orderByDesc('added_at')->paginate($request->integer('per_page', 100));
    }

    public function show(CrmKnowledgeFile $crmKnowledgeFile)
    {
        return $crmKnowledgeFile;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'category' => 'required|string|max:50',
            'file_type' => 'nullable|string|max:10',
            'file_url' => 'required_without:file|string|max:255',
            'size' => 'nullable|string|max:50',
            'added_at' => 'nullable|date',
            'file' => 'nullable|file|max:51200',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('knowledge-base', 'public');
            $data['file_url'] = Storage::url($path);
            $data['file_type'] = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            $data['size'] = $data['size'] ?? $this->formatSize($file->getSize());
            $data['added_at'] = $data['added_at'] ?? now();
            $data['name'] = $data['name'] ?: $file->getClientOriginalName();
        }

        if (empty($data['name'])) {
            return response()->json(['message' => 'Nazwa pliku jest wymagana.'], 422);
        }

        if (empty($data['file_type'])) {
            return response()->json(['message' => 'Typ pliku jest wymagany.'], 422);
        }

        $file = CrmKnowledgeFile::create($data);
        return response()->json($file, 201);
    }

    public function update(Request $request, CrmKnowledgeFile $crmKnowledgeFile)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:255',
            'category' => 'sometimes|required|string|max:50',
            'file_type' => 'nullable|string|max:10',
            'file_url' => 'nullable|string|max:255',
            'size' => 'nullable|string|max:50',
            'added_at' => 'nullable|date',
            'file' => 'nullable|file|max:51200',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('knowledge-base', 'public');
            $data['file_url'] = Storage::url($path);
            $data['file_type'] = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            $data['size'] = $data['size'] ?? $this->formatSize($file->getSize());
            $data['added_at'] = $data['added_at'] ?? now();
            if (!isset($data['name'])) {
                $data['name'] = $file->getClientOriginalName();
            }
        }

        $crmKnowledgeFile->update($data);
        return $crmKnowledgeFile;
    }

    public function destroy(CrmKnowledgeFile $crmKnowledgeFile)
    {
        $crmKnowledgeFile->delete();
        return response()->noContent();
    }

    public function download(CrmKnowledgeFile $crmKnowledgeFile)
    {
        $fileUrl = $crmKnowledgeFile->file_url ?? '';
        if (!$fileUrl) {
            return response()->json(['message' => 'Brak pliku do pobrania.'], 404);
        }

        $path = parse_url($fileUrl, PHP_URL_PATH) ?: $fileUrl;
        $path = preg_replace('#^/storage/#', '', $path);

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Plik nie istnieje.'], 404);
        }

        $filename = $crmKnowledgeFile->name ?: basename($path);
        return Storage::disk('public')->download($path, $filename);
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $kb = $bytes / 1024;
        if ($kb < 1024) {
            return number_format($kb, 1) . ' KB';
        }
        return number_format($kb / 1024, 1) . ' MB';
    }
}
