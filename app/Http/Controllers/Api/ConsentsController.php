<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConsentsController extends Controller
{
    public function index(Request $request)
    {
        $q = Consent::query();
        return $q->orderByDesc('id')->paginate($request->integer('per_page', 25));
    }

    public function show(Consent $consent)
    {
        return $consent;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:255|unique:consents,code',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'required' => 'nullable|boolean',
            'file' => 'nullable|file|mimes:pdf|max:10240',
        ]);
        $file = $request->file('file');
        unset($data['file']);
        $consent = Consent::create($data);
        if ($file) {
            $path = $file->storeAs("consents/{$consent->id}", $file->getClientOriginalName());
            $consent->update([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
        return response()->json($consent, 201);
    }

    public function update(Request $request, Consent $consent)
    {
        $data = $request->validate([
            'code' => 'sometimes|required|string|max:255|unique:consents,code,'.$consent->id,
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:255',
            'required' => 'nullable|boolean',
            'file' => 'nullable|file|mimes:pdf|max:10240',
            'file_remove' => 'nullable|boolean',
        ]);
        $file = $request->file('file');
        $remove = (bool) ($data['file_remove'] ?? false);
        unset($data['file'], $data['file_remove']);
        $consent->update($data);

        if ($remove && $consent->file_path) {
            Storage::delete($consent->file_path);
            $consent->update([
                'file_path' => null,
                'file_name' => null,
                'file_type' => null,
                'file_size' => null,
            ]);
        }

        if ($file) {
            if ($consent->file_path) {
                Storage::delete($consent->file_path);
            }
            $path = $file->storeAs("consents/{$consent->id}", $file->getClientOriginalName());
            $consent->update([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
        return $consent;
    }

    public function destroy(Consent $consent)
    {
        if ($consent->file_path) {
            Storage::delete($consent->file_path);
        }
        $consent->delete();
        return response()->noContent();
    }

    public function file(Request $request, Consent $consent)
    {
        if (!$consent->file_path || !Storage::exists($consent->file_path)) {
            return response()->json(['message' => 'Plik nie został znaleziony.'], 404);
        }
        if ($request->boolean('download')) {
            return Storage::download($consent->file_path, $consent->file_name ?: 'consent.pdf');
        }
        return response()->file(Storage::path($consent->file_path), [
            'Content-Type' => $consent->file_type ?: 'application/pdf',
        ]);
    }
}
