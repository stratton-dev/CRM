<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageSent;
use App\Services\Push\PushService;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    // GET /v1/chat/users — all system users except self
    public function users(): JsonResponse
    {
        $me = Auth::user();

        $users = User::query()
            ->where('id', '!=', $me->id)
            ->where(fn($q) => $q->whereNull('is_removed_from_structure')->orWhere('is_removed_from_structure', false))
            ->where(fn($q) => $q->whereNull('is_blocked')->orWhere('is_blocked', false))
            ->with('role:id,code,name')
            ->orderBy('name')
            ->get()
            ->map(fn(User $user) => [
                'id'               => $user->id,
                'supabaseId'       => $user->supabase_id,
                'name'             => $user->name,
                'role'             => $user->role?->code ?? $user->role_cached ?? null,
                'teamGroupPath'    => $user->team_group_path ?? null,
                'parentSupabaseId' => $user->parent_supabase_id ?? null,
            ]);

        return response()->json(['data' => $users]);
    }

    // POST /v1/chat/groups — find or create a named group conversation (general / team)
    public function ensureGroup(Request $request): JsonResponse
    {
        $request->validate([
            'key'  => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $me   = Auth::user();
        $key  = $request->input('key');
        $name = $request->input('name');

        $conv = DB::transaction(function () use ($me, $key, $name) {
            $conv = ChatConversation::firstOrCreate(
                ['key' => $key],
                ['type' => 'group', 'name' => $name, 'is_group' => true]
            );

            // Add current user as participant if not already
            if (!$conv->participants()->where('user_id', $me->id)->exists()) {
                $conv->participants()->attach($me->id);
            }

            return $conv;
        });

        return response()->json(['data' => ['id' => $conv->id, 'key' => $conv->key, 'name' => $conv->name]]);
    }

    // GET /v1/chat/conversations — all my conversations (DM + groups)
    public function conversations(): JsonResponse
    {
        $me = Auth::user();

        $conversations = ChatConversation::whereHas('participants', fn($q) => $q->where('user_id', $me->id))
            ->with([
                'participants:id,name,supabase_id',
                'lastMessage.sender:id,name',
            ])
            ->get()
            ->map(function (ChatConversation $conv) use ($me) {
                $otherParticipant = $conv->is_group
                    ? null
                    : ($conv->participants->firstWhere('id', '!=', $me->id) ?? $conv->participants->first());

                $lastMsg = $conv->lastMessage->first();

                $myPivot    = $conv->participants->firstWhere('id', $me->id);
                $lastReadAt = $myPivot?->pivot?->last_read_at;

                $unread = $lastReadAt
                    ? ChatMessage::where('conversation_id', $conv->id)
                        ->where('sender_id', '!=', $me->id)
                        ->where('created_at', '>', $lastReadAt)
                        ->count()
                    : ChatMessage::where('conversation_id', $conv->id)
                        ->where('sender_id', '!=', $me->id)
                        ->count();

                return [
                    'id'          => $conv->id,
                    'type'        => $conv->type,
                    'key'         => $conv->key,
                    'name'        => $conv->name ?? $otherParticipant?->name ?? 'Chat',
                    'isGroup'     => $conv->is_group,
                    'participant' => $otherParticipant ? [
                        'id'         => $otherParticipant->id,
                        'name'       => $otherParticipant->name,
                        'supabaseId' => $otherParticipant->supabase_id,
                    ] : null,
                    'lastMessage' => $lastMsg ? [
                        'body'       => $lastMsg->body,
                        'senderName' => $lastMsg->sender?->name,
                        'createdAt'  => $lastMsg->created_at->toIso8601String(),
                    ] : null,
                    'unread'      => $unread,
                ];
            });

        return response()->json(['data' => $conversations]);
    }

    // POST /v1/chat/conversations — find or create a direct conversation
    public function findOrCreate(Request $request): JsonResponse
    {
        $me = Auth::user();

        // Accept either integer user_id or string supabase_id
        if ($request->filled('supabase_id')) {
            $other = User::where('supabase_id', $request->input('supabase_id'))->firstOrFail();
            $otherId = $other->id;
        } else {
            $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
            $otherId = (int) $request->input('user_id');
        }

        if ($otherId === $me->id) {
            return response()->json(['error' => 'Cannot chat with yourself'], 422);
        }

        $existing = ChatConversation::where('type', 'direct')
            ->where('is_group', false)
            ->whereHas('participants', fn($q) => $q->where('user_id', $me->id))
            ->whereHas('participants', fn($q) => $q->where('user_id', $otherId))
            ->first();

        if ($existing) {
            return response()->json(['data' => ['id' => $existing->id]]);
        }

        $conv = DB::transaction(function () use ($me, $otherId) {
            $conv = ChatConversation::create(['type' => 'direct', 'is_group' => false]);
            $conv->participants()->attach([$me->id, $otherId]);
            return $conv;
        });

        return response()->json(['data' => ['id' => $conv->id]], 201);
    }

    // GET /v1/chat/conversations/{id}/messages
    public function messages(int $id): JsonResponse
    {
        $me = Auth::user();

        $conv = ChatConversation::whereHas('participants', fn($q) => $q->where('user_id', $me->id))
            ->findOrFail($id);

        $messages = ChatMessage::where('conversation_id', $conv->id)
            ->with('sender:id,name,supabase_id')
            ->orderBy('created_at')
            ->get()
            ->map(fn(ChatMessage $msg) => [
                'id'         => $msg->id,
                'senderId'   => $msg->sender_id,
                'senderName' => $msg->sender?->name ?? 'Unknown',
                'body'       => $msg->body,
                'type'       => $msg->type,
                'createdAt'  => $msg->created_at->toIso8601String(),
                'mine'       => $msg->sender_id === $me->id,
            ]);

        DB::table('chat_participants')
            ->where('conversation_id', $conv->id)
            ->where('user_id', $me->id)
            ->update(['last_read_at' => now()]);

        return response()->json(['data' => $messages]);
    }

    // POST /v1/chat/conversations/{id}/messages
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:4000']]);

        $me = Auth::user();

        $conv = ChatConversation::whereHas('participants', fn($q) => $q->where('user_id', $me->id))
            ->findOrFail($id);

        $message = ChatMessage::create([
            'conversation_id' => $conv->id,
            'sender_id'       => $me->id,
            'body'            => $request->input('body'),
            'type'            => 'text',
        ]);

        $message->load('sender:id,name');

        event(new ChatMessageSent($message));

        // Push notifications to all other participants
        $conv->loadMissing('participants:id');
        $otherUserIds = $conv->participants
            ->where('id', '!=', $me->id)
            ->pluck('id')
            ->all();

        if (!empty($otherUserIds)) {
            $pushTitle = $conv->is_group
                ? ($conv->name ?? 'Czat grupowy')
                : ($me->name ?? 'Wiadomość');
            $pushBody = strlen($message->body) > 80
                ? substr($message->body, 0, 80) . '…'
                : $message->body;

            app(PushService::class)->notifyUsers(
                $otherUserIds,
                $me->id,
                $pushTitle,
                ($conv->is_group ? ($me->name . ': ') : '') . $pushBody,
                ['type' => 'chat', 'conversationId' => $conv->id]
            );
        }

        return response()->json([
            'data' => [
                'id'         => $message->id,
                'senderId'   => $message->sender_id,
                'senderName' => $message->sender?->name,
                'body'       => $message->body,
                'type'       => $message->type,
                'createdAt'  => $message->created_at->toIso8601String(),
                'mine'       => true,
            ],
        ], 201);
    }

    // PUT /v1/chat/conversations/{id}/read
    public function markRead(int $id): JsonResponse
    {
        $me = Auth::user();

        ChatConversation::whereHas('participants', fn($q) => $q->where('user_id', $me->id))
            ->findOrFail($id);

        DB::table('chat_participants')
            ->where('conversation_id', $id)
            ->where('user_id', $me->id)
            ->update(['last_read_at' => now()]);

        return response()->json(['data' => ['ok' => true]]);
    }

    // POST /v1/chat/groups/create — user-initiated group with members
    public function createGroupWithMembers(Request $request): JsonResponse
    {
        $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'member_supabase_ids' => ['array'],
            'member_supabase_ids.*' => ['string'],
        ]);

        $me      = Auth::user();
        $name    = $request->input('name');
        $members = $request->input('member_supabase_ids', []);

        $conv = DB::transaction(function () use ($me, $name, $members) {
            $conv = ChatConversation::create([
                'type'     => 'group',
                'name'     => $name,
                'is_group' => true,
            ]);

            $conv->participants()->attach($me->id);

            if ($members) {
                $userIds = User::whereIn('supabase_id', $members)
                    ->where('id', '!=', $me->id)
                    ->pluck('id');
                if ($userIds->isNotEmpty()) {
                    $conv->participants()->attach($userIds);
                }
            }

            return $conv;
        });

        return response()->json(['data' => ['id' => $conv->id, 'name' => $conv->name]], 201);
    }

    // GET /v1/chat/conversations/{id}/members
    public function members(int $id): JsonResponse
    {
        $me = Auth::user();

        $conv = ChatConversation::whereHas('participants', fn($q) => $q->where('user_id', $me->id))
            ->findOrFail($id);

        $members = $conv->participants()->get()->map(fn(User $user) => [
            'id'         => (string) $user->supabase_id,
            'name'       => $user->name,
            'supabaseId' => $user->supabase_id,
            'role'       => $user->role_cached,
            'isMe'       => $user->id === $me->id,
        ]);

        return response()->json(['data' => $members]);
    }

    // POST /v1/chat/conversations/{id}/members
    public function addMember(Request $request, int $id): JsonResponse
    {
        $request->validate(['supabase_id' => ['required', 'string']]);

        $me = Auth::user();

        $conv = ChatConversation::where('is_group', true)
            ->whereHas('participants', fn($q) => $q->where('user_id', $me->id))
            ->findOrFail($id);

        $user = User::where('supabase_id', $request->input('supabase_id'))->firstOrFail();

        if (!$conv->participants()->where('user_id', $user->id)->exists()) {
            $conv->participants()->attach($user->id);
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    // DELETE /v1/chat/conversations/{id}/members/{supabaseId}
    public function removeMember(int $id, string $supabaseId): JsonResponse
    {
        $me = Auth::user();

        $conv = ChatConversation::where('is_group', true)
            ->whereHas('participants', fn($q) => $q->where('user_id', $me->id))
            ->findOrFail($id);

        $user = User::where('supabase_id', $supabaseId)->firstOrFail();
        $conv->participants()->detach($user->id);

        return response()->json(['data' => ['ok' => true]]);
    }

    // DELETE /v1/chat/conversations/{id} — leave conversation
    public function leave(int $id): JsonResponse
    {
        $me = Auth::user();

        $conv = ChatConversation::whereHas('participants', fn($q) => $q->where('user_id', $me->id))
            ->findOrFail($id);

        $conv->participants()->detach($me->id);

        return response()->json(['data' => ['ok' => true]]);
    }
}
