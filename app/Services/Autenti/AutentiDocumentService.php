<?php

namespace App\Services\Autenti;

use App\Models\ClientContact;
use App\Models\Document;
use Illuminate\Support\Arr;
use RuntimeException;

class AutentiDocumentService
{
    public function __construct(private readonly AutentiClientFactory $factory)
    {
    }

    public function createProcess(Document $document, array $context = []): Document
    {
        if (!$document->client) {
            $document->load('client.contacts');
        }

        $signer = $this->resolveSigner($document, $context);
        $payload = [
            'title' => $context['title'] ?? $document->type,
            'description' => $context['description'] ?? null,
            'processLanguage' => $context['process_language'] ?? config('autenti.default_language', 'pl'),
            'parties' => [
                [
                    'party' => [
                        'firstName' => $signer['first_name'],
                        'lastName' => $signer['last_name'],
                        'contacts' => [
                            [
                                'type' => 'CONTACT-TYPE:EMAIL',
                                'attributes' => [
                                    'email' => $signer['email'],
                                ],
                            ],
                        ],
                    ],
                    'role' => 'SIGNER',
                ],
            ],
            'files' => [],
            'tags' => $context['tags'] ?? [],
            'constraints' => $context['constraints'] ?? [],
            'flags' => $context['flags'] ?? [],
        ];

        $client = $this->factory->make();
        $response = $client->documentProcesses()->create($payload);
        $process = $response->json();

        $processId = $process['id'] ?? null;
        if (!$processId) {
            throw new RuntimeException('Autenti process id missing in response.');
        }

        $document->autenti_process_id = $processId;
        $document->autenti_status = $process['status'] ?? null;
        $document->autenti_last_event_at = now();
        $document->save();

        $filePath = $this->resolveFilePath((string) $document->file_path);
        $fileMeta = [
            'name' => $context['file_name'] ?? basename($filePath),
            'description' => $context['file_description'] ?? $payload['title'],
            'filePurpose' => $context['file_purpose'] ?? 'SOURCE_FILE',
        ];

        $fileHeaders = [];
        if (!empty($fileMeta['filePurpose'])) {
            $fileHeaders['X-File-Purpose'] = $fileMeta['filePurpose'];
        }

        $fileResponse = $client->files()->upload($processId, $filePath, $fileMeta, [
            'headers' => $fileHeaders,
        ]);
        $fileData = $fileResponse->json();
        if (!empty($fileData['id'])) {
            $document->autenti_file_id = $fileData['id'];
            $document->save();
        }

        if (!empty($context['action']) && is_array($context['action'])) {
            $client->actions()->perform($processId, $context['action']);
        }

        return $document;
    }

    public function syncProcess(Document $document): Document
    {
        if (!$document->autenti_process_id) {
            throw new RuntimeException('Document has no Autenti process id.');
        }

        $client = $this->factory->make();
        $response = $client->documentProcesses()->get($document->autenti_process_id);
        $data = $response->json();

        $document->autenti_status = $data['status'] ?? $document->autenti_status;
        $document->autenti_last_event_at = now();

        if ($this->isSigned($data)) {
            $document->signed_at = $document->signed_at ?? now();
        }

        $document->save();

        return $document;
    }

    public function handleCallback(array $payload): ?Document
    {
        $processId = $this->extractProcessId($payload);
        if (!$processId) {
            return null;
        }

        $document = Document::query()->where('autenti_process_id', $processId)->first();
        if (!$document) {
            return null;
        }

        $status = $this->extractStatus($payload);
        if ($status !== null) {
            $document->autenti_status = $status;
        }

        $document->autenti_last_event_at = now();

        if ($this->isSigned($payload)) {
            $document->signed_at = $document->signed_at ?? now();
        }

        $document->save();

        return $document;
    }

    private function resolveSigner(Document $document, array $context): array
    {
        $signer = Arr::get($context, 'signer');
        if (is_array($signer)) {
            return $this->normalizeSigner($signer);
        }

        $contact = $this->resolveClientContact($document);
        if ($contact) {
            return $this->normalizeSigner([
                'name' => $contact->name,
                'email' => $contact->email,
            ]);
        }

        throw new RuntimeException('Signer data is required to create Autenti process.');
    }

    private function resolveClientContact(Document $document): ?ClientContact
    {
        if (!$document->client) {
            return null;
        }

        $contacts = $document->client->contacts;
        if (!$contacts) {
            return null;
        }

        return $contacts->firstWhere('is_decision_maker', true) ?? $contacts->first();
    }

    private function normalizeSigner(array $data): array
    {
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            $name = trim((string) ($data['name'] ?? ''));
            if ($name !== '') {
                $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $firstName = $firstName ?: ($parts[0] ?? '');
                $lastName = $lastName ?: (count($parts) > 1 ? $parts[count($parts) - 1] : '');
            }
        }

        if ($firstName === '' || $lastName === '' || $email === '') {
            throw new RuntimeException('Signer first name, last name, and email are required.');
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => trim((string) ($data['phone'] ?? '')),
        ];
    }

    private function resolveFilePath(string $path): string
    {
        if ($path === '') {
            throw new RuntimeException('File path is empty.');
        }

        if (is_file($path)) {
            return $path;
        }

        $storagePath = storage_path('app/' . ltrim($path, '/'));
        if (is_file($storagePath)) {
            return $storagePath;
        }

        throw new RuntimeException('File path not found: ' . $path);
    }

    private function extractProcessId(array $payload): ?string
    {
        $keys = [
            'documentProcessId',
            'document_process_id',
            'document-process-id',
            'processId',
            'process_id',
            'documentProcess.id',
        ];

        foreach ($keys as $key) {
            $value = data_get($payload, $key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function extractStatus(array $payload): ?string
    {
        $status = data_get($payload, 'status');
        if (is_string($status) && $status !== '') {
            return $status;
        }

        $status = data_get($payload, 'documentProcess.status');
        if (is_string($status) && $status !== '') {
            return $status;
        }

        return null;
    }

    private function isSigned(array $payload): bool
    {
        $status = (string) ($this->extractStatus($payload) ?? '');
        if (in_array($status, ['COMPLETED', 'SIGNED', 'FINISHED'], true)) {
            return true;
        }

        $eventType = data_get($payload, 'eventType');
        if (is_string($eventType) && in_array($eventType, ['SIGNATURE', 'DOCUMENT_SIGNED'], true)) {
            return true;
        }

        return false;
    }
}
