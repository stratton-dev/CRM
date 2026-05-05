<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\CrmMailConfig;
use App\Jobs\ExtractMemoriesJob;
use App\Services\Ai\AiToolsService;
use App\Services\Ai\KnowledgeSearchService;
use App\Services\Ai\MemoryService;
use App\Services\Ai\RolePromptService;
use App\Services\Crm\CrmMailboxService;
use App\Models\AiChatFile;
use App\Services\Ai\FileUploadService;
use App\Services\Ai\TextExtractionService;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Facades\Tool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AiChatController extends Controller
{
    private array $capturedToolResults = [];

    public function __construct(
        private RolePromptService      $rolePromptService,
        private AiToolsService         $toolsService,
        private KnowledgeSearchService $knowledgeSearch,
        private CrmMailboxService      $mailboxService,
        private MemoryService          $memoryService,
        private FileUploadService      $fileUploadService,
        private TextExtractionService  $textExtractionService,
    ) {}

    public function indexConversations(Request $request): JsonResponse
    {
        $user = Auth::user();
        $conversations = AiConversation::where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'model', 'total_tokens', 'updated_at']);

        return response()->json(['data' => $conversations]);
    }

    public function createConversation(Request $request): JsonResponse
    {
        $user = Auth::user();
        $conv = AiConversation::create([
            'user_id' => $user->id,
            'title'   => $request->input('title', 'Nowa rozmowa'),
            'model'   => config('ai.model'),
        ]);
        return response()->json(['data' => $conv], 201);
    }

    public function showConversation(int $id): JsonResponse
    {
        $user = Auth::user();
        $conv = AiConversation::where('id', $id)->where('user_id', $user->id)
            ->with('messages')
            ->firstOrFail();
        return response()->json(['data' => $conv]);
    }

    public function destroyConversation(int $id): JsonResponse
    {
        $user = Auth::user();
        AiConversation::where('id', $id)->where('user_id', $user->id)->delete();
        return response()->json(['message' => 'Usunięto.']);
    }

    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:10000',
            'file_id' => 'nullable|integer',
        ]);
        $user = Auth::user();

        $conv = AiConversation::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        $userMessageContent = $request->input('message');
        if ($request->filled('file_id')) {
            $fileRecord = AiChatFile::where('id', $request->input('file_id'))
                ->where('user_id', $user->id)
                ->first();
            if ($fileRecord) {
                $userMessageContent = "[Załączony plik: {$fileRecord->original_name} (ID: {$fileRecord->id})]\n\n" . $userMessageContent;
            }
        }

        AiMessage::create([
            'conversation_id' => $conv->id,
            'role'            => 'user',
            'content'         => $userMessageContent,
        ]);

        $history = $conv->messages()->orderByDesc('id')->limit(20)->get()->sortBy('id')->values();

        $kbChunks         = $this->knowledgeSearch->search($request->input('message'), 2);
        $knowledgeContext = '';
        if (!empty($kbChunks)) {
            $combined = implode("\n\n---\n\n", $kbChunks);
            $knowledgeContext = mb_substr($combined, 0, 2000);
        }

        // Pobierz pasujące wspomnienia z długoterminowej pamięci
        $memoryContext = $this->memoryService->retrieveRelevant($user, $request->input('message'));

        $systemPrompt = $this->rolePromptService->getSystemPrompt($user, $knowledgeContext, $memoryContext);

        $prismMessages = [];
        foreach ($history as $msg) {
            if ($msg->role === 'user') {
                $prismMessages[] = new \Prism\Prism\ValueObjects\Messages\UserMessage($msg->content ?? '');
            } elseif ($msg->role === 'assistant') {
                $prismMessages[] = new \Prism\Prism\ValueObjects\Messages\AssistantMessage($msg->content ?? '');
            }
        }

        $this->capturedToolResults = [];
        $tools = $this->buildTools($user);

        try {
            $response = Prism::text()
                ->using(Provider::Anthropic, config('ai.model', 'claude-sonnet-4-6'))
                ->withSystemPrompt($systemPrompt)
                ->withMessages($prismMessages)
                ->withTools($tools)
                ->withMaxTokens(config('ai.max_tokens', 4096))
                ->withMaxSteps(5)
                ->generate();

            $assistantContent = $response->text;
            $toolResults      = [];

            if ($response->toolCalls && count($response->toolCalls) > 0) {
                foreach ($response->toolCalls as $toolCall) {
                    $toolResults[] = [
                        'tool'   => $toolCall->name,
                        'result' => $this->capturedToolResults[$toolCall->name] ?? null,
                    ];
                }
            }

            $aiMessage = AiMessage::create([
                'conversation_id' => $conv->id,
                'role'            => 'assistant',
                'content'         => $assistantContent,
                'tool_calls'      => !empty($toolResults) ? $toolResults : null,
                'tokens_used'     => $response->usage->outputTokens ?? 0,
            ]);

            $totalTokens = ($response->usage->inputTokens ?? 0) + ($response->usage->outputTokens ?? 0);
            $conv->increment('total_tokens', $totalTokens);

            if ($history->count() === 1 && $conv->title === 'Nowa rozmowa') {
                $conv->update(['title' => mb_substr($request->input('message'), 0, 60)]);
            }

            // Asynchronicznie wyodrębnij wspomnienia z tej wymiany
            ExtractMemoriesJob::dispatch($conv->id, $user->id)
                ->onQueue('default')
                ->delay(now()->addSeconds(3)); // małe opóźnienie żeby message był już zapisany

            return response()->json([
                'data' => [
                    'id'          => $aiMessage->id,
                    'role'        => 'assistant',
                    'content'     => $assistantContent,
                    'tool_calls'  => $toolResults,
                    'tokens_used' => $totalTokens,
                    'actions'     => $this->extractFrontendActions($toolResults),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('AiChatController: błąd Prism', ['error' => $e->getMessage()]);
            $msg = $e->getMessage();
            if (str_contains($msg, 'rate limit') || str_contains($msg, 'rate_limit') || str_contains($msg, 'overloaded')) {
                $retryAfter = 30;
                if (preg_match('/retry after (\d+)/i', $msg, $m)) {
                    $retryAfter = (int) $m[1];
                }
                return response()->json([
                    'error' => "Zbyt duże obciążenie AI — spróbuj ponownie za {$retryAfter} sekund.",
                    'retry_after' => $retryAfter,
                ], 429);
            }
            return response()->json([
                'error' => 'Błąd komunikacji z AI: ' . $msg,
            ], 500);
        }
    }

    public function quickMessage(Request $request): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:5000']);
        $user = Auth::user();

        $kbChunks         = $this->knowledgeSearch->search($request->input('message'), 3);
        $knowledgeContext = empty($kbChunks) ? '' : implode("\n\n---\n\n", $kbChunks);
        $systemPrompt     = $this->rolePromptService->getSystemPrompt($user, $knowledgeContext);

        try {
            $response = Prism::text()
                ->using(Provider::Anthropic, config('ai.model_mini', 'claude-haiku-4-5-20251001'))
                ->withSystemPrompt($systemPrompt)
                ->withPrompt($request->input('message'))
                ->withMaxTokens(1024)
                ->generate();

            return response()->json(['data' => ['content' => $response->text]]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function uploadFile(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|max:20480']);
        $user = Auth::user();

        try {
            $record = $this->fileUploadService->store(
                $request->file('file'),
                $user->id,
                $request->input('conversation_id') ? (int) $request->input('conversation_id') : null
            );

            return response()->json([
                'data' => [
                    'file_id'   => (string) $record->id,
                    'filename'  => $record->original_name,
                    'size'      => $record->size_bytes,
                    'mime_type' => $record->mime_type,
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function downloadFile(int $id): mixed
    {
        $user   = Auth::user();
        $record = AiChatFile::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        if (!Storage::disk('local')->exists($record->stored_path)) {
            return response()->json(['error' => 'Plik wygasł lub nie istnieje.'], 404);
        }

        return Storage::disk('local')->download(
            $record->stored_path,
            $record->original_name,
            ['Content-Type' => $record->mime_type]
        );
    }

    private function buildTools(mixed $user): array
    {
        $ts       = $this->toolsService;
        $mb       = $this->mailboxService;
        $captured = &$this->capturedToolResults;

        return [
            // ── CRM tools ────────────────────────────────────────────────────────────
            Tool::as('get_my_leads')
                ->for('Pobiera moje aktualne leady/szanse sprzedażowe z CRM')
                ->withStringParameter('status', 'Status: all, active, won, lost. Domyślnie: active')
                ->using(function (string $status = 'active') use ($ts, $user, &$captured) {
                    $result = $ts->getMyLeads($user, $status);
                    $captured['get_my_leads'] = $result;
                    return json_encode($result);
                }),

            Tool::as('get_client_card')
                ->for('Pobiera kartę klienta (dane kontaktowe, historia, notatki)')
                ->withNumberParameter('client_id', 'ID klienta w systemie CRM')
                ->using(function (int $client_id) use ($ts, $user, &$captured) {
                    $result = $ts->getClientCard($user, $client_id);
                    $captured['get_client_card'] = $result;
                    return json_encode($result);
                }),

            Tool::as('get_today_meetings')
                ->for('Pobiera listę dzisiejszych spotkań z kalendarza')
                ->using(function () use ($ts, $user, &$captured) {
                    $result = $ts->getTodayMeetings($user);
                    $captured['get_today_meetings'] = $result;
                    return json_encode($result);
                }),

            Tool::as('create_calendar_event')
                ->for('Tworzy nowe wydarzenie/spotkanie w kalendarzu CRM')
                ->withStringParameter('title', 'Tytuł spotkania')
                ->withStringParameter('date', 'Data w formacie YYYY-MM-DD')
                ->withStringParameter('time', 'Godzina w formacie HH:MM (opcjonalnie)')
                ->withStringParameter('location', 'Miejsce spotkania (opcjonalnie)')
                ->withStringParameter('description', 'Opis lub notatki (opcjonalnie)')
                ->using(function (string $title, string $date, string $time = '', string $location = '', string $description = '') use ($ts, $user, &$captured) {
                    $result = $ts->createCalendarEvent($user, compact('title', 'date', 'time', 'location', 'description'));
                    $captured['create_calendar_event'] = $result;
                    return json_encode($result);
                }),

            Tool::as('send_email_to_client')
                ->for('Przygotowuje email do klienta CRM — otwiera okno compose (klient musi mieć ID w CRM)')
                ->withNumberParameter('client_id', 'ID klienta')
                ->withStringParameter('subject', 'Temat emaila')
                ->withStringParameter('body', 'Treść emaila (może zawierać HTML)')
                ->using(function (int $client_id, string $subject, string $body) use ($ts, $user, &$captured) {
                    $result = $ts->sendEmailToClient($user, $client_id, $subject, $body);
                    $captured['send_email_to_client'] = $result;
                    return json_encode($result);
                }),

            Tool::as('send_internal_notification')
                ->for('Wysyła wewnętrzne powiadomienie do innego użytkownika CRM')
                ->withNumberParameter('target_user_id', 'ID użytkownika docelowego')
                ->withStringParameter('message', 'Treść powiadomienia')
                ->using(function (int $target_user_id, string $message) use ($ts, $user, &$captured) {
                    $result = $ts->sendInternalNotification($user, $target_user_id, $message);
                    $captured['send_internal_notification'] = $result;
                    return json_encode($result);
                }),

            Tool::as('get_my_stats')
                ->for('Pobiera moje statystyki z CRM (liczba leadów, spotkań, wyniki)')
                ->using(function () use ($ts, $user, &$captured) {
                    $result = $ts->getMyStats($user);
                    $captured['get_my_stats'] = $result;
                    return json_encode($result);
                }),

            // ── Email / mailbox tools ─────────────────────────────────────────────────
            Tool::as('list_emails')
                ->for('Listuje emaile ze skrzynki pocztowej użytkownika. Zwraca nadawcę, temat, datę, UID i status przeczytania.')
                ->withStringParameter('folder', 'Folder: INBOX (domyślnie), SENT, TRASH, DRAFTS, SPAM')
                ->withNumberParameter('limit', 'Liczba wiadomości, max 50 (domyślnie 20)')
                ->using(function (string $folder = 'INBOX', int $limit = 20) use ($mb, $user, &$captured) {
                    $config = CrmMailConfig::where('user_id', $user->id)->first();
                    if (!$config) {
                        $result = ['success' => false, 'error' => 'Brak konfiguracji skrzynki. Skonfiguruj pocztę w Ustawieniach.'];
                        $captured['list_emails'] = $result;
                        return json_encode($result);
                    }
                    try {
                        $data     = $mb->listMessages($config, strtoupper($folder), min($limit, 50), 0);
                        $messages = $data['messages'] ?? (array) $data;
                        $result   = [
                            'success' => true,
                            'folder'  => $folder,
                            'count'   => count($messages),
                            'emails'  => array_map(fn ($m) => [
                                'uid'        => $m['uid'] ?? $m['id'] ?? null,
                                'from'       => trim(($m['fromName'] ?? '') . ' <' . ($m['fromEmail'] ?? '') . '>'),
                                'subject'    => $m['subject'] ?? '(brak tematu)',
                                'date'       => $m['date'] ?? null,
                                'read'       => $m['read'] ?? false,
                                'has_attach' => !empty($m['attachments']),
                            ], $messages),
                        ];
                        $captured['list_emails'] = $result;
                        return json_encode($result);
                    } catch (\Exception $e) {
                        $result = ['success' => false, 'error' => $e->getMessage()];
                        $captured['list_emails'] = $result;
                        return json_encode($result);
                    }
                }),

            Tool::as('read_email')
                ->for('Odczytuje pełną treść konkretnego emaila po UID. Użyj list_emails żeby poznać UID.')
                ->withStringParameter('folder', 'Folder emaila: INBOX, SENT, TRASH itp.')
                ->withNumberParameter('uid', 'UID wiadomości (z list_emails)')
                ->using(function (string $folder, int $uid) use ($mb, $user, &$captured) {
                    $config = CrmMailConfig::where('user_id', $user->id)->first();
                    if (!$config) {
                        $result = ['success' => false, 'error' => 'Brak konfiguracji skrzynki.'];
                        $captured['read_email'] = $result;
                        return json_encode($result);
                    }
                    try {
                        $data     = $mb->getMessageBody($config, strtoupper($folder), $uid);
                        $bodyText = preg_replace('/\s+/', ' ', strip_tags($data['body'] ?? ''));
                        $result   = [
                            'success'     => true,
                            'uid'         => $uid,
                            'folder'      => $folder,
                            'from'        => trim(($data['fromName'] ?? '') . ' <' . ($data['fromEmail'] ?? '') . '>'),
                            'to'          => $data['toEmail'] ?? null,
                            'subject'     => $data['subject'] ?? '(brak tematu)',
                            'date'        => $data['date'] ?? null,
                            'body_text'   => mb_substr($bodyText, 0, 3000),
                            'attachments' => array_map(
                                fn ($a) => is_array($a) ? ($a['filename'] ?? $a['name'] ?? 'plik') : (string) $a,
                                $data['attachments'] ?? []
                            ),
                        ];
                        try { $mb->markRead($config, strtoupper($folder), $uid); } catch (\Exception) {}
                        $captured['read_email'] = $result;
                        return json_encode($result);
                    } catch (\Exception $e) {
                        $result = ['success' => false, 'error' => $e->getMessage()];
                        $captured['read_email'] = $result;
                        return json_encode($result);
                    }
                }),

            Tool::as('search_emails')
                ->for('Wyszukuje emaile po słowie kluczowym (temat, nadawca). Zwraca pasujące wiadomości z UID.')
                ->withStringParameter('keyword', 'Słowo kluczowe do wyszukania')
                ->withStringParameter('folder', 'Folder: INBOX (domyślnie), SENT, TRASH')
                ->using(function (string $keyword, string $folder = 'INBOX') use ($mb, $user, &$captured) {
                    $config = CrmMailConfig::where('user_id', $user->id)->first();
                    if (!$config) {
                        $result = ['success' => false, 'error' => 'Brak konfiguracji skrzynki.'];
                        $captured['search_emails'] = $result;
                        return json_encode($result);
                    }
                    try {
                        $data     = $mb->listMessages($config, strtoupper($folder), 50, 0);
                        $messages = $data['messages'] ?? (array) $data;
                        $kw       = mb_strtolower($keyword);
                        $filtered = array_values(array_filter($messages, fn ($m) =>
                            str_contains(mb_strtolower($m['subject'] ?? ''), $kw) ||
                            str_contains(mb_strtolower($m['fromEmail'] ?? ''), $kw) ||
                            str_contains(mb_strtolower($m['fromName'] ?? ''), $kw)
                        ));
                        $result = [
                            'success' => true,
                            'keyword' => $keyword,
                            'folder'  => $folder,
                            'count'   => count($filtered),
                            'emails'  => array_map(fn ($m) => [
                                'uid'     => $m['uid'] ?? $m['id'] ?? null,
                                'from'    => trim(($m['fromName'] ?? '') . ' <' . ($m['fromEmail'] ?? '') . '>'),
                                'subject' => $m['subject'] ?? '(brak tematu)',
                                'date'    => $m['date'] ?? null,
                                'read'    => $m['read'] ?? false,
                            ], $filtered),
                        ];
                        $captured['search_emails'] = $result;
                        return json_encode($result);
                    } catch (\Exception $e) {
                        $result = ['success' => false, 'error' => $e->getMessage()];
                        $captured['search_emails'] = $result;
                        return json_encode($result);
                    }
                }),

            Tool::as('send_email')
                ->for('Wysyła email bezpośrednio ze skrzynki użytkownika przez SMTP. Używaj do wysyłania do dowolnego adresu.')
                ->withStringParameter('to', 'Adres email odbiorcy')
                ->withStringParameter('subject', 'Temat wiadomości')
                ->withStringParameter('body', 'Treść wiadomości (HTML lub tekst)')
                ->using(function (string $to, string $subject, string $body) use ($mb, $user, &$captured) {
                    $config = CrmMailConfig::where('user_id', $user->id)->first();
                    if (!$config) {
                        $result = ['success' => false, 'error' => 'Brak konfiguracji SMTP. Skonfiguruj pocztę w Ustawieniach.'];
                        $captured['send_email'] = $result;
                        return json_encode($result);
                    }
                    try {
                        $mb->sendMessage($config, ['to' => $to, 'subject' => $subject, 'body' => $body]);
                        $result = ['success' => true, 'message' => "Email do {$to} wysłany pomyślnie.", 'to' => $to, 'subject' => $subject];
                        $captured['send_email'] = $result;
                        return json_encode($result);
                    } catch (\Exception $e) {
                        $result = ['success' => false, 'error' => 'Nie udało się wysłać: ' . $e->getMessage()];
                        $captured['send_email'] = $result;
                        return json_encode($result);
                    }
                }),

            Tool::as('reply_to_email')
                ->for('Odpowiada na konkretny email (po UID). Pobiera oryginalną wiadomość i wysyła odpowiedź z cytowaniem.')
                ->withStringParameter('folder', 'Folder oryginalnej wiadomości (np. INBOX)')
                ->withNumberParameter('original_uid', 'UID oryginalnej wiadomości')
                ->withStringParameter('reply_body', 'Treść odpowiedzi (HTML lub tekst)')
                ->using(function (string $folder, int $original_uid, string $reply_body) use ($mb, $user, &$captured) {
                    $config = CrmMailConfig::where('user_id', $user->id)->first();
                    if (!$config) {
                        $result = ['success' => false, 'error' => 'Brak konfiguracji skrzynki.'];
                        $captured['reply_to_email'] = $result;
                        return json_encode($result);
                    }
                    try {
                        $original = $mb->getMessageBody($config, strtoupper($folder), $original_uid);
                        $replyTo  = $original['replyTo'] ?? $original['fromEmail'] ?? '';
                        $subject  = $original['subject'] ?? '';
                        if (!str_starts_with(strtolower($subject), 're:')) {
                            $subject = 'Re: ' . $subject;
                        }
                        $quotedBody = $reply_body
                            . '<br><br><blockquote style="border-left:3px solid #ccc;padding-left:1em;color:#555">'
                            . ($original['body'] ?? '')
                            . '</blockquote>';

                        $mb->sendMessage($config, [
                            'to'        => $replyTo,
                            'subject'   => $subject,
                            'body'      => $quotedBody,
                            'inReplyTo' => $original['messageId'] ?? null,
                        ]);
                        try { $mb->markRead($config, strtoupper($folder), $original_uid); } catch (\Exception) {}

                        $result = ['success' => true, 'message' => "Odpowiedź do {$replyTo} wysłana pomyślnie.", 'to' => $replyTo, 'subject' => $subject];
                        $captured['reply_to_email'] = $result;
                        return json_encode($result);
                    } catch (\Exception $e) {
                        $result = ['success' => false, 'error' => 'Błąd odpowiedzi: ' . $e->getMessage()];
                        $captured['reply_to_email'] = $result;
                        return json_encode($result);
                    }
                }),

            Tool::as('forward_email')
                ->for('Przekazuje email do nowego odbiorcy z opcjonalną notatką.')
                ->withStringParameter('folder', 'Folder oryginalnej wiadomości')
                ->withNumberParameter('original_uid', 'UID oryginalnej wiadomości')
                ->withStringParameter('to', 'Adres email do przekazania')
                ->withStringParameter('note', 'Opcjonalna notatka poprzedzająca treść')
                ->using(function (string $folder, int $original_uid, string $to, string $note = '') use ($mb, $user, &$captured) {
                    $config = CrmMailConfig::where('user_id', $user->id)->first();
                    if (!$config) {
                        $result = ['success' => false, 'error' => 'Brak konfiguracji skrzynki.'];
                        $captured['forward_email'] = $result;
                        return json_encode($result);
                    }
                    try {
                        $original = $mb->getMessageBody($config, strtoupper($folder), $original_uid);
                        $subject  = $original['subject'] ?? '';
                        if (!str_starts_with(strtolower($subject), 'fwd:')) {
                            $subject = 'Fwd: ' . $subject;
                        }
                        $fwdBody = ($note ? '<p>' . htmlspecialchars($note) . '</p><br>' : '')
                            . '<p style="color:#555">---------- Wiadomość przekazana ----------<br>'
                            . 'Od: ' . htmlspecialchars($original['fromName'] ?? '') . ' &lt;' . htmlspecialchars($original['fromEmail'] ?? '') . '&gt;<br>'
                            . 'Data: ' . ($original['date'] ?? '') . '<br>'
                            . 'Temat: ' . htmlspecialchars($original['subject'] ?? '') . '</p><br>'
                            . ($original['body'] ?? '');

                        $mb->sendMessage($config, ['to' => $to, 'subject' => $subject, 'body' => $fwdBody]);

                        $result = ['success' => true, 'message' => "Email przekazany do {$to} pomyślnie.", 'to' => $to, 'subject' => $subject];
                        $captured['forward_email'] = $result;
                        return json_encode($result);
                    } catch (\Exception $e) {
                        $result = ['success' => false, 'error' => 'Błąd przekazywania: ' . $e->getMessage()];
                        $captured['forward_email'] = $result;
                        return json_encode($result);
                    }
                }),

            Tool::as('mark_email_read')
                ->for('Oznacza wiadomość jako przeczytaną.')
                ->withStringParameter('folder', 'Folder wiadomości')
                ->withNumberParameter('uid', 'UID wiadomości')
                ->using(function (string $folder, int $uid) use ($mb, $user, &$captured) {
                    $config = CrmMailConfig::where('user_id', $user->id)->first();
                    if (!$config) {
                        $result = ['success' => false, 'error' => 'Brak konfiguracji skrzynki.'];
                        $captured['mark_email_read'] = $result;
                        return json_encode($result);
                    }
                    try {
                        $mb->markRead($config, strtoupper($folder), $uid);
                        $result = ['success' => true, 'message' => 'Wiadomość oznaczona jako przeczytana.'];
                        $captured['mark_email_read'] = $result;
                        return json_encode($result);
                    } catch (\Exception $e) {
                        $result = ['success' => false, 'error' => $e->getMessage()];
                        $captured['mark_email_read'] = $result;
                        return json_encode($result);
                    }
                }),

            Tool::as('delete_email')
                ->for('Przenosi wiadomość do kosza (TRASH).')
                ->withStringParameter('folder', 'Aktualny folder wiadomości')
                ->withNumberParameter('uid', 'UID wiadomości do usunięcia')
                ->using(function (string $folder, int $uid) use ($mb, $user, &$captured) {
                    $config = CrmMailConfig::where('user_id', $user->id)->first();
                    if (!$config) {
                        $result = ['success' => false, 'error' => 'Brak konfiguracji skrzynki.'];
                        $captured['delete_email'] = $result;
                        return json_encode($result);
                    }
                    try {
                        $mb->moveMessage($config, strtoupper($folder), $uid, 'TRASH');
                        $result = ['success' => true, 'message' => 'Wiadomość przeniesiona do kosza.'];
                        $captured['delete_email'] = $result;
                        return json_encode($result);
                    } catch (\Exception $e) {
                        $result = ['success' => false, 'error' => $e->getMessage()];
                        $captured['delete_email'] = $result;
                        return json_encode($result);
                    }
                }),

            Tool::as('create_event_from_email')
                ->for('Tworzy wydarzenie w kalendarzu na podstawie informacji z emaila. Użyj gdy email zawiera informacje o spotkaniu, spotkaniu, terminie lub wydarzeniu.')
                ->withStringParameter('title', 'Tytuł spotkania')
                ->withStringParameter('date', 'Data spotkania YYYY-MM-DD')
                ->withStringParameter('time', 'Godzina HH:MM (opcjonalnie)')
                ->withStringParameter('location', 'Miejsce (opcjonalnie)')
                ->withStringParameter('description', 'Opis lub skrót treści emaila (opcjonalnie)')
                ->using(function (string $title, string $date, string $time = '', string $location = '', string $description = '') use ($ts, $user, &$captured) {
                    $result = $ts->createCalendarEvent($user, compact('title', 'date', 'time', 'location', 'description'));
                    $captured['create_event_from_email'] = $result;
                    return json_encode($result);
                }),
        ];
    }

    private function extractFrontendActions(array $toolResults): array
    {
        $actions = [];
        foreach ($toolResults as $tr) {
            $result = is_array($tr['result']) ? $tr['result'] : json_decode($tr['result'] ?? '{}', true);
            if (isset($result['open_compose']) && $result['open_compose']) {
                $actions[] = [
                    'type'    => 'open_compose',
                    'to'      => $result['to'] ?? '',
                    'subject' => $result['subject'] ?? '',
                ];
            }
            if (($tr['tool'] ?? '') === 'create_calendar_event' && !empty($result['success'])) {
                $actions[] = ['type' => 'refresh_calendar'];
            }
        }
        return $actions;
    }
}
