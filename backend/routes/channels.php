<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('team.{teamId}', function ($user, $teamId) {
    if ($user->role_cached === 'ADMIN') {
        return true;
    }

    return (string) $user->team_id === (string) $teamId;
});

Broadcast::channel('user.{keycloakId}', function ($user, $keycloakId) {
    if ($user->role_cached === 'ADMIN') {
        return true;
    }

    if ($user->keycloak_id && (string) $user->keycloak_id === (string) $keycloakId) {
        return true;
    }

    return (string) $user->id === (string) $keycloakId;
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
