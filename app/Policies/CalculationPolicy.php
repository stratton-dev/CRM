<?php

namespace App\Policies;

use App\Models\Calculation;
use App\Models\User;

class CalculationPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role?->code, ['advisor', 'manager'], true);
    }

    public function view(User $user, Calculation $calculation): bool
    {
        return (bool) $user->active;
    }
}
