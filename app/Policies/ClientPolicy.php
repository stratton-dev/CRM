<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function view(User $user, Client $client): bool
    {
        return $user->organization_id === $client->organization_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->code, ['advisor', 'manager', 'director'], true);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->role?->code === 'director';
    }
}
