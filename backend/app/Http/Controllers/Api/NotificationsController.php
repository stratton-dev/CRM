<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\Notifications\NotificationCreated;
use App\Events\Notifications\NotificationDeleted;
use App\Events\Notifications\NotificationUpdated;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    public function sent(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $q = Notification::query()
            ->where('sender_id', $user->id)
            ->with(['user', 'sender'])
            ->latest();

        if ($type = $request->string('type')->toString()) {
            $q->where('type', $type);
        }

        return $q->paginate($request->integer('per_page', 25));
    }

    public function batchStore(Request $request)
    {
        $data = $request->validate([
            'recipients' => 'required|array',
            'recipients.*' => 'exists:users,keycloak_id',
            'type' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'scheduled_at' => 'nullable|date',
        ]);

        $senderId = $request->user()?->id;

        // Resolve database IDs from Keycloak IDs
        // We use pluck('id', 'keycloak_id') to map input to DB IDs if needed,
        // but here we just need the list of valid DB IDs.
        $resolvedUserIds = \App\Models\User::whereIn('keycloak_id', $data['recipients'])->pluck('id');

        $notifications = [];
        $now = now();

        foreach ($resolvedUserIds as $recipientId) {
            $notifData = [
                'user_id' => $recipientId,
                'sender_id' => $senderId,
                'type' => $data['type'],
                'title' => $data['title'],
                'body' => $data['body'],
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Allow bulk insert for performance if no events needed,
            // but we need events for realtime updates usually.
            // Using create one by one to fire implementation events nicely.
            $notification = Notification::create($notifData);
            if ($notification->user) {
                event(new NotificationCreated($notification, $notification->user));
            }
            $notifications[] = $notification;
        }

        return response()->json(['count' => count($notifications)], 201);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            if ($keycloakId = $request->string('user_keycloak_id')->toString()) {
                $user = \App\Models\User::query()->where('keycloak_id', $keycloakId)->first();
            }
        }

        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $updated = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['count' => $updated]);
    }

    public function index(Request $request)
    {
        $q = Notification::query()->with('user:id,keycloak_id,name,email', 'sender:id,keycloak_id,name,email');

        if ($userId = $request->integer('user_id')) {
            $q->where('user_id', $userId);
        } elseif ($keycloakId = $request->string('user_keycloak_id')->toString()) {
            $user = \App\Models\User::query()->where('keycloak_id', $keycloakId)->first();
            if ($user) $q->where('user_id', $user->id);
        } else {
             // By default, show notifications for the current user if no param is passed
             $q->where('user_id', $request->user()->id);
        }
        if ($type = $request->string('type')->toString()) {
            $q->where('type', $type);
        }
        if (!is_null($request->boolean('unread'))) {
            if ($request->boolean('unread')) {
                $q->whereNull('read_at');
            }
        }

        // Only show notifications that are scheduled for now or in the past
        $q->where(function ($query) {
            $query->whereNull('scheduled_at')
                  ->orWhere('scheduled_at', '<=', now());
        });

        return $q->latest()->paginate($request->integer('per_page', 25));
    }

    public function show(Notification $notification)
    {
        return $notification;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'user_keycloak_id' => 'nullable|string',
            'type' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'read_at' => 'nullable|date',
            'scheduled_at' => 'nullable|date',
        ]);
        if (empty($data['user_id']) && !empty($data['user_keycloak_id'])) {
            $user = \App\Models\User::query()->where('keycloak_id', $data['user_keycloak_id'])->first();
            if ($user) {
                $data['user_id'] = $user->id;
            }
        }
        if (empty($data['user_id'])) {
            return response()->json(['message' => 'User not found.'], 422);
        }
        unset($data['user_keycloak_id']);

        $data['sender_id'] = $request->user()?->id;

        $notification = Notification::create($data);
        $notification->load('user', 'sender');
        if ($notification->user) {
            event(new NotificationCreated($notification, $notification->user));
        }
        return response()->json($notification, 201);
    }

    public function update(Request $request, Notification $notification)
    {
        $data = $request->validate([
            'type' => 'sometimes|string|max:255',
            'title' => 'sometimes|string|max:255',
            'body' => 'sometimes|string',
            'read_at' => 'nullable|date',
            'scheduled_at' => 'nullable|date',
        ]);

        // Handle _method spoofing if PUT is used
        if ($request->has('read_at') && !$request->filled('read_at')) {
             // If read_at is present but empty, it might be an issue with how JS sends it, but validation handles date.
        }

        $notification->update($data);
        $notification->refresh()->load('user');
        if ($notification->user) {
            event(new NotificationUpdated($notification, $notification->user));
        }
        return $notification;
    }

    public function destroy(Notification $notification)
    {
        $notification->load('user');
        $user = $notification->user;
        $notification->delete();
        if ($user) {
            event(new NotificationDeleted($notification, $user));
        }
        return response()->noContent();
    }

}
