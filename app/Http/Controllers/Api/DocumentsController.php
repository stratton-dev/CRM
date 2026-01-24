<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Autenti\AutentiDocumentService;
use Illuminate\Http\Request;

class DocumentsController extends Controller
{
    public function __construct(private readonly AutentiDocumentService $autenti)
    {
    }

    public function index(Request $request)
    {
        $q = Document::query()->with('client:id,name');

        if ($clientId = $request->integer('client_id')) {
            $q->where('client_id', $clientId);
        }
        if ($type = $request->string('type')->toString()) {
            $q->where('type', $type);
        }

        return $q->latest()->paginate($request->integer('per_page', 25));
    }

    public function show(Document $document)
    {
        return $document->load('client:id,name');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:companies,id',
            'type' => 'required|string|max:255',
            'file_path' => 'required|string|max:255',
            'signed_at' => 'nullable|date',
            'send_to_autenti' => 'nullable|boolean',
            'autenti' => 'nullable|array',
            'autenti.title' => 'nullable|string|max:255',
            'autenti.description' => 'nullable|string|max:1000',
            'autenti.process_language' => 'nullable|string|max:10',
            'autenti.signer' => 'nullable|array',
            'autenti.signer.first_name' => 'nullable|string|max:255',
            'autenti.signer.last_name' => 'nullable|string|max:255',
            'autenti.signer.name' => 'nullable|string|max:255',
            'autenti.signer.email' => 'nullable|email|max:255',
            'autenti.signer.phone' => 'nullable|string|max:50',
            'autenti.tags' => 'nullable|array',
            'autenti.constraints' => 'nullable|array',
            'autenti.flags' => 'nullable|array',
            'autenti.action' => 'nullable|array',
            'autenti.file_name' => 'nullable|string|max:255',
            'autenti.file_description' => 'nullable|string|max:1000',
            'autenti.file_purpose' => 'nullable|string|max:100',
        ]);
        $sendToAutenti = (bool) ($data['send_to_autenti'] ?? false);
        $autentiPayload = $data['autenti'] ?? [];
        unset($data['send_to_autenti'], $data['autenti']);

        $document = Document::create($data);

        if ($sendToAutenti && config('autenti.enabled')) {
            $this->autenti->createProcess($document, $autentiPayload);
        }

        return response()->json($document, 201);
    }

    public function update(Request $request, Document $document)
    {
        $data = $request->validate([
            'client_id' => 'sometimes|exists:companies,id',
            'type' => 'sometimes|string|max:255',
            'file_path' => 'sometimes|string|max:255',
            'signed_at' => 'nullable|date',
            'send_to_autenti' => 'nullable|boolean',
            'autenti' => 'nullable|array',
            'autenti.title' => 'nullable|string|max:255',
            'autenti.description' => 'nullable|string|max:1000',
            'autenti.process_language' => 'nullable|string|max:10',
            'autenti.signer' => 'nullable|array',
            'autenti.signer.first_name' => 'nullable|string|max:255',
            'autenti.signer.last_name' => 'nullable|string|max:255',
            'autenti.signer.name' => 'nullable|string|max:255',
            'autenti.signer.email' => 'nullable|email|max:255',
            'autenti.signer.phone' => 'nullable|string|max:50',
            'autenti.tags' => 'nullable|array',
            'autenti.constraints' => 'nullable|array',
            'autenti.flags' => 'nullable|array',
            'autenti.action' => 'nullable|array',
            'autenti.file_name' => 'nullable|string|max:255',
            'autenti.file_description' => 'nullable|string|max:1000',
            'autenti.file_purpose' => 'nullable|string|max:100',
        ]);
        $sendToAutenti = (bool) ($data['send_to_autenti'] ?? false);
        $autentiPayload = $data['autenti'] ?? [];
        unset($data['send_to_autenti'], $data['autenti']);

        $document->update($data);

        if ($sendToAutenti && config('autenti.enabled') && !$document->autenti_process_id) {
            $this->autenti->createProcess($document, $autentiPayload);
        }

        return $document;
    }

    public function destroy(Document $document)
    {
        $document->delete();
        return response()->noContent();
    }

    public function syncAutenti(Document $document)
    {
        $document = $this->autenti->syncProcess($document);
        return $document->refresh();
    }
}
