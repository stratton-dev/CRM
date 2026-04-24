<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

class OfferPolicy
{
    public function create(User $user): bool
    {
        return $user->role?->code === 'advisor';
    }

    public function expire(User $user, Offer $offer): bool
    {
        return in_array($user->role?->code, ['manager', 'director'], true);
    }
}
