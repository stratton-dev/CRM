<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('team.{teamId}', function ($user, $teamId) {
    if ($user->role_cached === 'ADMIN') {
        return true;
    }

    return (string) $user->team_id === (string) $teamId;
});

Broadcast::channel('user.{supabaseId}', function ($user, $supabaseId) {
    if ($user->role_cached === 'ADMIN') {
        return true;
    }

    if ($user->supabase_id && (string) $user->supabase_id === (string) $supabaseId) {
        return true;
    }

    return (string) $user->id === (string) $supabaseId;
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    return \App\Models\ChatConversation::where('id', (int) $conversationId)
        ->whereHas('participants', fn($q) => $q->where('user_id', $user->id))
        ->exists();
});
