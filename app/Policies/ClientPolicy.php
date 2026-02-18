<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function view(User $user, Client $client): bool
    {
        if (in_array($user->role_cached ?? $user->role?->code, ['ADMIN', 'DIRECTOR', 'director', 'admin'], true)) {
            return true;
        }
        return $user->organization_id === $client->organization_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role_cached ?? $user->role?->code, ['advisor', 'manager', 'director', 'DIRECTOR', 'ADMIN', 'admin'], true);
    }

    public function update(User $user, Client $client): bool
    {
        if (in_array($user->role_cached ?? $user->role?->code, ['ADMIN', 'DIRECTOR', 'director', 'admin'], true)) {
            return true;
        }
        return $user->organization_id === $client->organization_id;
    }

    public function delete(User $user, Client $client): bool
    {
        return in_array($user->role_cached ?? $user->role?->code, ['director', 'DIRECTOR', 'ADMIN', 'admin'], true);
    }
}
