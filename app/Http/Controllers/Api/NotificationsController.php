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
    public function index(Request $request)
    {
        $q = Notification::query()->with('user:id,keycloak_id');

        if ($userId = $request->integer('user_id')) {
            $q->where('user_id', $userId);
        } elseif ($keycloakId = $request->string('user_keycloak_id')->toString()) {
            $user = \App\Models\User::query()->where('keycloak_id', $keycloakId)->first();
            if ($user) $q->where('user_id', $user->id);
        }
        if ($type = $request->string('type')->toString()) {
            $q->where('type', $type);
        }
        if (!is_null($request->boolean('unread'))) {
            if ($request->boolean('unread')) {
                $q->whereNull('read_at');
            }
        }

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
        $notification = Notification::create($data);
        $notification->load('user');
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
        ]);
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

    public function markAllRead(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'user_keycloak_id' => 'nullable|string',
        ]);
        $userId = $data['user_id'] ?? null;
        if (!$userId && !empty($data['user_keycloak_id'])) {
            $user = \App\Models\User::query()->where('keycloak_id', $data['user_keycloak_id'])->first();
            if ($user) $userId = $user->id;
        }
        if (!$userId) {
            return response()->json(['message' => 'User not found.'], 422);
        }
        Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        return response()->noContent();
    }
}
