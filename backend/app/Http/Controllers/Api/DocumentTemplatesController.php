<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Models\Company;
use App\Services\Autenti\DocumentTemplateRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentTemplatesController extends Controller
{
    public function index()
    {
        return DocumentTemplate::query()->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'type' => 'nullable|in:pdf,html',
            'html_content' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf|max:10240',
            'is_active' => 'nullable|boolean',
        ]);

        $type = $data['type'] ?? ($request->hasFile('file') ? 'pdf' : 'html');
        $slug = $this->uniqueSlug($data['slug'] ?? $data['name']);

        $template = new DocumentTemplate([
            'name' => $data['name'],
            'slug' => $slug,
            'type' => $type,
            'html_content' => $type === 'html' ? ($data['html_content'] ?? '') : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if ($type === 'pdf') {
            $file = $request->file('file');
            if (!$file) {
                return response()->json(['message' => 'PDF file is required for pdf templates.'], 422);
            }
            $path = $file->store('document-templates');
            $template->file_path = $path;
        }

        $template->save();

        return response()->json($template, 201);
    }

    public function update(Request $request, DocumentTemplate $documentTemplate)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255',
            'type' => 'nullable|in:pdf,html',
            'html_content' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf|max:10240',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($data['name'])) {
            $documentTemplate->name = $data['name'];
        }
        if (isset($data['slug'])) {
            $documentTemplate->slug = $this->uniqueSlug($data['slug'], $documentTemplate->id);
        }
        if (isset($data['is_active'])) {
            $documentTemplate->is_active = (bool) $data['is_active'];
        }

        if (isset($data['type'])) {
            $documentTemplate->type = $data['type'];
        }

        if ($documentTemplate->type === 'html' && array_key_exists('html_content', $data)) {
            $documentTemplate->html_content = $data['html_content'] ?? '';
        }

        if ($documentTemplate->type === 'pdf' && $request->hasFile('file')) {
            if ($documentTemplate->file_path) {
                Storage::delete($documentTemplate->file_path);
            }
            $documentTemplate->file_path = $request->file('file')->store('document-templates');
        }

        $documentTemplate->save();

        return $documentTemplate;
    }

    public function destroy(DocumentTemplate $documentTemplate)
    {
        if ($documentTemplate->file_path) {
            Storage::delete($documentTemplate->file_path);
        }

        $documentTemplate->delete();

        return response()->noContent();
    }

    public function download(DocumentTemplate $documentTemplate)
    {
        if ($documentTemplate->type !== 'pdf' || !$documentTemplate->file_path) {
            return response()->json(['message' => 'No PDF file for this template.'], 404);
        }

        return Storage::download($documentTemplate->file_path, $documentTemplate->slug . '.pdf');
    }

    public function suggestions()
    {
        $map = (array) config('autenti.template_map', []);
        $labels = [
            'nda' => 'NDA',
            'cooperationAgreement' => 'Umowa wspolpracy',
            'careerPath' => 'Sciezka kariery',
        ];
        $suggestions = [];
        foreach ($map as $key => $slug) {
            $suggestions[] = [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'slug' => $slug,
            ];
        }

        return $suggestions;
    }

    public function preview(Request $request, DocumentTemplate $documentTemplate, DocumentTemplateRenderer $renderer)
    {
        if ($documentTemplate->type === 'pdf') {
            if (!$documentTemplate->file_path) {
                return response()->json(['message' => 'No PDF file for this template.'], 404);
            }
            $path = Storage::path($documentTemplate->file_path);
            return response()->file($path, ['Content-Type' => 'application/pdf']);
        }

        $userId = $request->string('user_keycloak_id')->toString();
        $user = $userId !== ''
            ? User::query()->where('keycloak_id', $userId)->first()
            : null;
        if (!$user) {
            $user = $request->integer('user_id') ? User::query()->find($request->integer('user_id')) : null;
        }
        if (!$user) {
            $user = User::query()->first();
        }
        if (!$user) {
            return response()->json(['message' => 'Missing user for preview.'], 422);
        }

        $client = null;
        if ($request->integer('client_id')) {
            $client = Company::query()->find($request->integer('client_id'));
        }

        $path = $renderer->render($documentTemplate, $user, [
            'client' => $client,
        ]);

        return response()->file($path, ['Content-Type' => 'application/pdf']);
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $slug = Str::slug($value);
        $base = $slug;
        $i = 2;
        while (DocumentTemplate::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
