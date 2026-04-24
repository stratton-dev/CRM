<?php

namespace App\Services\Autenti;

use App\Models\AutentiDocument;
use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AutentiOnboardingService
{
    public function __construct(
        private readonly AutentiClientFactory $factory,
        private readonly DocumentTemplateRenderer $renderer
    ) {
    }

    public function startForUser(User $user, array $documentsJson, string $initiatorKeycloakId): AutentiDocument
    {
        $templates = $this->resolveTemplates($documentsJson);
        if ($templates->isEmpty()) {
            throw new RuntimeException('No document templates selected.');
        }

        $client = $this->factory->make();
        $signer = $this->resolveSigner($user, $documentsJson);

        $payload = [
            'title' => 'Dokumenty onboardingowe',
            'description' => null,
            'processLanguage' => config('autenti.default_language', 'pl'),
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
            'tags' => [],
            'constraints' => [],
            'flags' => [],
        ];

        $response = $client->documentProcesses()->create($payload);
        $process = $response->json();
        $processId = $process['id'] ?? null;
        if (!$processId) {
            throw new RuntimeException('Autenti process id missing in response.');
        }

        return DB::transaction(function () use ($client, $processId, $process, $templates, $user, $initiatorKeycloakId) {
            $docList = $templates->pluck('name')->implode(', ');

            $autentiDoc = AutentiDocument::create([
                'user_id' => $user->id,
                'user_keycloak_id' => $user->keycloak_id,
                'initiator_keycloak_id' => $initiatorKeycloakId,
                'recipient_name' => $user->name,
                'recipient_email' => $user->email,
                'document_list' => $docList,
                'status' => 'SENT',
                'sent_at' => now(),
                'autenti_process_id' => $processId,
                'autenti_status' => $process['status'] ?? null,
                'autenti_last_event_at' => now(),
            ]);

            foreach ($templates as $template) {
                $filePath = $this->renderer->render($template, $user);
                $client->files()->upload($processId, $filePath, [
                    'name' => basename($filePath),
                    'description' => $template->name,
                    'filePurpose' => 'SOURCE_FILE',
                ], [
                    'headers' => [
                        'X-File-Purpose' => 'SOURCE_FILE',
                    ],
                ]);
            }

            $client->actions()->perform($processId, []);

            return $autentiDoc;
        });
    }

    public function sync(AutentiDocument $doc): AutentiDocument
    {
        if (!$doc->autenti_process_id) {
            throw new RuntimeException('Autenti process id missing.');
        }

        $client = $this->factory->make();
        $response = $client->documentProcesses()->get($doc->autenti_process_id);
        $data = $response->json();

        $doc->autenti_status = $data['status'] ?? $doc->autenti_status;
        $doc->autenti_last_event_at = now();

        $doc->status = $this->mapStatus($data);
        if ($doc->status === 'SIGNED' && !$doc->signed_at) {
            $doc->signed_at = now();
            $this->markUserSigned($doc->user);
        }

        $doc->save();

        return $doc;
    }

    public function handleCallback(array $payload): ?AutentiDocument
    {
        $processId = $this->extractProcessId($payload);
        if (!$processId) {
            return null;
        }

        $doc = AutentiDocument::query()->where('autenti_process_id', $processId)->first();
        if (!$doc) {
            return null;
        }

        $doc->autenti_status = $this->extractStatus($payload) ?? $doc->autenti_status;
        $doc->autenti_last_event_at = now();
        $doc->status = $this->mapStatus($payload);

        if ($doc->status === 'SIGNED' && !$doc->signed_at) {
            $doc->signed_at = now();
            $this->markUserSigned($doc->user);
        }

        if ($doc->status === 'VIEWED' && !$doc->viewed_at) {
            $doc->viewed_at = now();
        }

        $doc->save();

        return $doc;
    }

    private function resolveTemplates(array $documentsJson)
    {
        $map = (array) config('autenti.template_map', []);
        $slugs = [];

        if (!empty($documentsJson['nda']) && !empty($map['nda'])) {
            $slugs[] = $map['nda'];
        }
        if (!empty($documentsJson['cooperationAgreement']) && !empty($map['cooperationAgreement'])) {
            $slugs[] = $map['cooperationAgreement'];
        }
        if (!empty($documentsJson['careerPath']) && !empty($map['careerPath'])) {
            $slugs[] = $map['careerPath'];
        }
        if (!empty($documentsJson['otherTemplateId'])) {
            $slugs[] = $documentsJson['otherTemplateId'];
        }

        if (empty($slugs)) {
            return collect();
        }

        return DocumentTemplate::query()
            ->whereIn('slug', $slugs)
            ->where('is_active', true)
            ->get();
    }

    private function resolveSigner(User $user, array $documentsJson): array
    {
        $name = trim((string) $user->name);
        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstName = $parts[0] ?? $name;
        $lastName = count($parts) > 1 ? $parts[count($parts) - 1] : '';

        $overrides = Arr::get($documentsJson, 'signer', []);
        if (is_array($overrides) && (!empty($overrides['first_name']) || !empty($overrides['last_name']))) {
            $firstName = $overrides['first_name'] ?? $firstName;
            $lastName = $overrides['last_name'] ?? $lastName;
        }

        return [
            'first_name' => $firstName ?: $user->name,
            'last_name' => $lastName ?: ' ',
            'email' => $user->email,
        ];
    }

    private function mapStatus(array $payload): string
    {
        $status = (string) ($this->extractStatus($payload) ?? '');
        if ($status === 'COMPLETED') {
            return 'SIGNED';
        }
        if (in_array($status, ['DECLINED', 'WITHDRAWN', 'REJECTED'], true)) {
            return 'REJECTED';
        }

        $flags = (array) ($payload['flags'] ?? $payload['documentProcess']['flags'] ?? []);
        if (in_array('FLAG:VIEWED', $flags, true)) {
            return 'VIEWED';
        }

        return 'SENT';
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

    private function markUserSigned(?User $user): void
    {
        if (!$user) {
            return;
        }

        $user->contract_status = 'SIGNED';
        $user->save();
    }
}
