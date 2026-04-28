<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmMailConfig;
use App\Models\CrmMailFolder;
use App\Models\CrmMailMessage;
use App\Services\Crm\CrmMailboxService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
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
            $this->mailbox->testConnection($config);
            return response()->json(['data' => ['ok' => true, 'imap_host' => $config->imap_host]]);
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

        try {
            $this->mailbox->sendMessage($config, $data);
        } catch (\RuntimeException $exception) {
            \Log::channel('mail')->warning('Mailbox send failed', [
                'user_id' => $user->id,
                'to' => $data['to'] ?? null,
                'message' => $exception->getMessage(),
            ]);
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        \Log::channel('mail')->info('Mailbox send ok', [
            'user_id' => $user->id,
            'to' => $data['to'] ?? null,
            'subject' => $data['subject'] ?? null,
        ]);

        return response()->json(['data' => ['ok' => true]]);
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

        try {
            $this->mailbox->markRead($config, $folderKey, (int) $uid);
        } catch (\RuntimeException $exception) {
            \Log::channel('mail')->warning('Mailbox markRead failed', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'message' => $exception->getMessage(),
            ]);
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    public function showBody(Request $request, string $messageId)
    {
        $user = $request->user();
        $config = CrmMailConfig::query()->where('user_id', $user->id)->first();
        if (!$config) {
            return response()->json(['message' => 'Brak konfiguracji poczty.'], 422);
        }

        if (!str_contains($messageId, ':')) {
            return response()->json(['message' => 'Invalid message id.'], 422);
        }
        [$folderKey, $uid] = explode(':', $messageId, 2);
        if (!ctype_digit($uid)) {
            return response()->json(['message' => 'Invalid message id.'], 422);
        }

        try {
            $data = $this->mailbox->getMessageBody($config, $folderKey, (int) $uid);
        } catch (RuntimeException $exception) {
            \Log::channel('mail')->warning('Mailbox getBody failed', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'message' => $exception->getMessage(),
            ]);
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        \Log::channel('mail')->info('Mailbox getBody ok', [
            'user_id' => $user->id,
            'message_id' => $messageId,
            'body_len' => strlen($data['body'] ?? ''),
        ]);
        return response()->json(['data' => $data]);
    }

}

