<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmMailConfig;
use App\Models\CrmMailJob;
use App\Models\CrmMailFolder;
use App\Models\CrmMailMessage;
use App\Services\Crm\CrmMailboxService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CrmMailboxController extends Controller
{
    public function __construct(private readonly CrmMailboxService $mailbox)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $config = CrmMailConfig::query()->where('user_id', $user->id)->first();
        if (!$config) {
            return response()->json(['message' => 'Brak konfiguracji poczty.'], 422);
        }

        $folder = strtoupper($request->string('folder')->toString() ?: 'INBOX');
        if (!in_array($folder, ['INBOX', 'SENT', 'TRASH'], true)) {
            return response()->json(['message' => 'Unsupported folder.'], 422);
        }
        $limit = $request->integer('limit', 50);

        if (env('IMAP_READ_FROM_DB', false)) {
            $messages = CrmMailMessage::query()
                ->where('user_id', $user->id)
                ->where('folder_key', $folder)
                ->orderByDesc('sent_at')
                ->limit($limit)
                ->get()
                ->map(static function (CrmMailMessage $message) {
                    return [
                        'id' => $message->folder_key.':'.$message->uid,
                        'fromName' => $message->from_name ?? '',
                        'fromEmail' => $message->from_email ?? '',
                        'toEmail' => $message->to_email ?? '',
                        'subject' => $message->subject ?? '(bez tematu)',
                        'body' => $message->body_html ?: ($message->body_text ? '<pre>'.e($message->body_text).'</pre>' : ''),
                        'attachments' => $message->attachments ?? [],
                        'date' => optional($message->sent_at)->toISOString() ?? $message->created_at->toISOString(),
                        'read' => (bool) $message->read,
                        'folder' => $message->folder_key,
                    ];
                })
                ->values();

            return response()->json(['data' => $messages]);
        }

        if (env('IMAP_DEBUG', false)) {
            \Log::channel('mail')->info('Mailbox list request', [
                'user_id' => $user->id,
                'folder' => $folder,
                'limit' => $limit,
            ]);
        }

        try {
            $payload = $this->mailbox->listMessages($config, $folder, $limit);
        } catch (RuntimeException $exception) {
            \Log::channel('mail')->warning('Mailbox list failed', [
                'user_id' => $user->id,
                'folder' => $folder,
                'message' => $exception->getMessage(),
            ]);
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if (is_array($payload) && array_key_exists('messages', $payload)) {
            return response()->json([
                'data' => $payload['messages'],
                'meta' => $payload['meta'] ?? null,
            ]);
        }

        return response()->json(['data' => $payload]);
    }

    public function folders(Request $request)
    {
        $user = $request->user();
        $config = CrmMailConfig::query()->where('user_id', $user->id)->first();
        if (!$config) {
            return response()->json(['message' => 'Brak konfiguracji poczty.'], 422);
        }

        if (env('IMAP_READ_FROM_DB', false)) {
            $folders = CrmMailFolder::query()
                ->where('user_id', $user->id)
                ->orderBy('path')
                ->get()
                ->map(static function (CrmMailFolder $folder) {
                    return [
                        'path' => $folder->path,
                        'name' => $folder->name ?: $folder->path,
                        'flags' => $folder->flags ?? [],
                        'listed' => (bool) $folder->listed,
                        'specialUse' => $folder->special_use,
                    ];
                })
                ->values();
            return response()->json(['data' => $folders]);
        }

        if (env('IMAP_DEBUG', false)) {
            \Log::channel('mail')->info('Mailbox folders request', [
                'user_id' => $user->id,
            ]);
        }

        try {
            $folders = $this->mailbox->listFoldersCached($config, $user->id);
        } catch (RuntimeException $exception) {
            \Log::channel('mail')->warning('Mailbox folders failed', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $folders]);
    }

    public function test(Request $request)
    {
        $user = $request->user();
        $config = CrmMailConfig::query()->where('user_id', $user->id)->first();
        if (!$config) {
            return response()->json(['message' => 'Brak konfiguracji poczty.'], 422);
        }

        try {
            $payload = [
                'user_id' => $user->id,
                'config' => $this->nodeConfig($config),
                'diagnostics' => $request->boolean('diagnostics'),
            ];
            $response = $this->nodeRequest('/test', $payload);
            return response()->json(['data' => $response['data'] ?? $response]);
        } catch (RuntimeException $exception) {
            \Log::channel('mail')->warning('Mailbox test failed', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
            return response()->json(['message' => $exception->getMessage()], 422);
        }

    }
    public function send(Request $request)
    {
        $user = $request->user();
        $config = CrmMailConfig::query()->where('user_id', $user->id)->first();
        if (!$config) {
            return response()->json(['message' => 'Brak konfiguracji poczty.'], 422);
        }

        $data = $request->validate([
            'to' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:255',
            'attachments' => 'nullable|array',
            'attachments.*.filename' => 'required_with:attachments|string|max:255',
            'attachments.*.content' => 'nullable|string',
            'attachments.*.content_type' => 'nullable|string|max:255',
            'attachments.*.encoding' => 'nullable|string|max:20',
            'attachments.*.html' => 'nullable|string',
            'attachments.*.convert_to_pdf' => 'nullable|boolean',
        ]);

        $attachments = [];
        if (!empty($data['attachments']) && is_array($data['attachments'])) {
            foreach ($data['attachments'] as $attachment) {
                $filename = $attachment['filename'] ?? 'attachment';
                $contentType = $attachment['content_type'] ?? 'application/octet-stream';
                $encoding = $attachment['encoding'] ?? 'base64';

                if (!empty($attachment['html']) && !empty($attachment['convert_to_pdf'])) {
                    $dompdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
                    $dompdf->loadHtml((string) $attachment['html']);
                    $dompdf->render();
                    $pdf = $dompdf->output();
                    $attachments[] = [
                        'filename' => preg_replace('/\.pdf$/i', '', $filename) . '.pdf',
                        'content' => base64_encode($pdf),
                        'content_type' => 'application/pdf',
                        'encoding' => 'base64',
                    ];
                    continue;
                }

                if (!empty($attachment['content'])) {
                    $attachments[] = [
                        'filename' => $filename,
                        'content' => $attachment['content'],
                        'content_type' => $contentType,
                        'encoding' => $encoding,
                    ];
                }
            }
        }

        if (!empty($attachments)) {
            $data['attachments'] = $attachments;
        } else {
            unset($data['attachments']);
        }

        $job = CrmMailJob::create([
            'user_id' => $user->id,
            'type' => 'send',
            'payload' => [
                'config' => $this->nodeConfig($config),
                'message' => $data,
            ],
            'status' => 'pending',
            'scheduled_at' => now(),
        ]);

        return response()->json(['data' => ['job_id' => $job->id, 'status' => $job->status]], 202);
    }

    public function mark(Request $request, string $messageId)
    {
        $user = $request->user();
        $config = CrmMailConfig::query()->where('user_id', $user->id)->first();
        if (!$config) {
            return response()->json(['message' => 'Brak konfiguracji poczty.'], 422);
        }

        $data = $request->validate([
            'read' => 'required|boolean',
        ]);

        if (!$data['read']) {
            return response()->json(['message' => 'Only marking as read is supported.'], 422);
        }

        if (!str_contains($messageId, ':')) {
            return response()->json(['message' => 'Invalid message id.'], 422);
        }
        [$folderKey, $uid] = explode(':', $messageId, 2);
        if (!ctype_digit($uid)) {
            return response()->json(['message' => 'Invalid message id.'], 422);
        }

        $job = CrmMailJob::create([
            'user_id' => $user->id,
            'type' => 'mark',
            'payload' => [
                'config' => $this->nodeConfig($config),
                'folder_key' => $folderKey,
                'uid' => (int) $uid,
            ],
            'status' => 'pending',
            'scheduled_at' => now(),
        ]);

        return response()->json(['data' => ['job_id' => $job->id, 'status' => $job->status]], 202);
    }

    private function nodeConfig(CrmMailConfig $config): array
    {
        return [
            'imap' => [
                'host' => $config->imap_host,
                'port' => $config->imap_port,
                'secure' => (bool) $config->imap_secure,
                'auth' => [
                    'user' => $config->imap_username,
                    'pass' => $config->imap_password,
                ],
                'folders' => [
                    'INBOX' => $config->imap_inbox_folder,
                    'SENT' => $config->imap_sent_folder,
                    'TRASH' => $config->imap_trash_folder,
                ],
            ],
            'smtp' => [
                'host' => $config->smtp_host,
                'port' => $config->smtp_port,
                'secure' => (bool) $config->smtp_secure,
                'auth' => [
                    'user' => $config->smtp_username,
                    'pass' => $config->smtp_password,
                ],
                'from' => [
                    'name' => $config->from_name,
                    'email' => $config->from_email,
                ],
            ],
        ];
    }

    private function nodeRequest(string $path, array $payload): array
    {
        $baseUrl = rtrim((string) env('IMAP_NODE_URL', ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('IMAP node URL is not configured.');
        }
        $token = (string) env('IMAP_SERVICE_TOKEN', '');
        $response = Http::withHeaders([
            'X-IMAP-SERVICE-TOKEN' => $token,
        ])->timeout(20)->post($baseUrl.$path, $payload);

        if (!$response->successful()) {
            $message = $response->json('message') ?: $response->body();
            throw new RuntimeException(is_string($message) && $message !== '' ? $message : 'IMAP node request failed.');
        }

        return is_array($response->json()) ? $response->json() : ['data' => $response->body()];
    }
}
