<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AiToolsService;
use App\Services\Ai\KnowledgeSearchService;
use App\Services\Ai\RolePromptService;
use EchoLabs\Prism\Enums\Provider;
use EchoLabs\Prism\Prism;
use EchoLabs\Prism\Tool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AiChatController extends Controller
{
    public function __construct(
        private RolePromptService    $rolePromptService,
        private AiToolsService       $toolsService,
        private KnowledgeSearchService $knowledgeSearch,
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
        $request->validate(['message' => 'required|string|max:10000']);
        $user = Auth::user();

        $conv = AiConversation::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        AiMessage::create([
            'conversation_id' => $conv->id,
            'role'            => 'user',
            'content'         => $request->input('message'),
        ]);

        $history = $conv->messages()->orderBy('id')->get();

        $kbChunks       = $this->knowledgeSearch->search($request->input('message'));
        $knowledgeContext = empty($kbChunks) ? '' : implode("\n\n---\n\n", $kbChunks);

        $systemPrompt = $this->rolePromptService->getSystemPrompt($user, $knowledgeContext);

        $prismMessages = [];
        foreach ($history as $msg) {
            if ($msg->role === 'user') {
                $prismMessages[] = new \EchoLabs\Prism\ValueObjects\Messages\UserMessage($msg->content ?? '');
            } elseif ($msg->role === 'assistant') {
                $prismMessages[] = new \EchoLabs\Prism\ValueObjects\Messages\AssistantMessage($msg->content ?? '');
            }
        }

        $tools = $this->buildTools($user);

        try {
            $response = Prism::text()
                ->using(Provider::Anthropic, config('ai.model', 'claude-sonnet-4-6'))
                ->withSystemPrompt($systemPrompt)
                ->withMessages($prismMessages)
                ->withTools($tools)
                ->withMaxTokens(config('ai.max_tokens', 4096))
                ->generate();

            $assistantContent = $response->text;
            $toolResults      = [];

            if ($response->toolCalls && count($response->toolCalls) > 0) {
                foreach ($response->toolCalls as $toolCall) {
                    $toolResult = $this->executeToolCall($user, $toolCall->name, $toolCall->arguments());
                    $toolResults[] = [
                        'tool'   => $toolCall->name,
                        'result' => $toolResult,
                    ];
                }
                if (!empty($toolResults)) {
                    $toolSummary = $this->formatToolResults($toolResults);
                    $assistantContent = ($assistantContent ? $assistantContent . "\n\n" : '') . $toolSummary;
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
            return response()->json([
                'error' => 'Błąd komunikacji z AI: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function quickMessage(Request $request): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:5000']);
        $user = Auth::user();

        $kbChunks       = $this->knowledgeSearch->search($request->input('message'), 3);
        $knowledgeContext = empty($kbChunks) ? '' : implode("\n\n---\n\n", $kbChunks);
        $systemPrompt   = $this->rolePromptService->getSystemPrompt($user, $knowledgeContext);

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

    private function buildTools(mixed $user): array
    {
        $ts = $this->toolsService;

        return [
            Tool::as('get_my_leads')
                ->for('Pobiera moje aktualne leady/szanse sprzedażowe z CRM')
                ->withStringParameter('status', 'Status: all, active, won, lost. Domyślnie: active')
                ->using(fn(string $status = 'active') => json_encode($ts->getMyLeads($user, $status))),

            Tool::as('get_client_card')
                ->for('Pobiera kartę klienta (dane kontaktowe, historia, notatki)')
                ->withNumberParameter('client_id', 'ID klienta w systemie CRM')
                ->using(fn(int $client_id) => json_encode($ts->getClientCard($user, $client_id))),

            Tool::as('get_today_meetings')
                ->for('Pobiera listę dzisiejszych spotkań z kalendarza')
                ->using(fn() => json_encode($ts->getTodayMeetings($user))),

            Tool::as('create_calendar_event')
                ->for('Tworzy nowe wydarzenie/spotkanie w kalendarzu CRM')
                ->withStringParameter('title', 'Tytuł spotkania')
                ->withStringParameter('date', 'Data w formacie YYYY-MM-DD')
                ->withStringParameter('time', 'Godzina w formacie HH:MM (opcjonalnie)')
                ->withStringParameter('location', 'Miejsce spotkania (opcjonalnie)')
                ->withStringParameter('description', 'Opis lub notatki (opcjonalnie)')
                ->using(fn(string $title, string $date, string $time = '', string $location = '', string $description = '')
                    => json_encode($ts->createCalendarEvent($user, compact('title', 'date', 'time', 'location', 'description')))),

            Tool::as('send_email_to_client')
                ->for('Przygotowuje email do klienta — otwiera okno compose')
                ->withNumberParameter('client_id', 'ID klienta')
                ->withStringParameter('subject', 'Temat emaila')
                ->withStringParameter('body', 'Treść emaila (może zawierać HTML)')
                ->using(fn(int $client_id, string $subject, string $body)
                    => json_encode($ts->sendEmailToClient($user, $client_id, $subject, $body))),

            Tool::as('send_internal_notification')
                ->for('Wysyła wewnętrzne powiadomienie do innego użytkownika CRM')
                ->withNumberParameter('target_user_id', 'ID użytkownika docelowego')
                ->withStringParameter('message', 'Treść powiadomienia')
                ->using(fn(int $target_user_id, string $message)
                    => json_encode($ts->sendInternalNotification($user, $target_user_id, $message))),

            Tool::as('get_my_stats')
                ->for('Pobiera moje statystyki z CRM (liczba leadów, spotkań, wyniki)')
                ->using(fn() => json_encode($ts->getMyStats($user))),
        ];
    }

    private function executeToolCall(mixed $user, string $name, array $args): mixed
    {
        return ['executed' => $name, 'args' => $args];
    }

    private function formatToolResults(array $toolResults): string
    {
        return '';
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
        }
        return $actions;
    }
}
