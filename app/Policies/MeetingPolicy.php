<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;
use App\Services\Auth\TokenContext;
use App\Services\Structure\StructureService;

class MeetingPolicy
{
    public function view(User $user, Meeting $meeting): bool
    {
        if (!$user->can('meetings.view')) {
            return false;
        }

        if (($user->role?->code ?? '') === 'ADMIN') {
            return true;
        }

        if ($meeting->user_id === $user->id) {
            return true;
        }

        $context = app(TokenContext::class);
        $structure = app(StructureService::class);
        $userIds = $structure->listUsers($context)->pluck('id')->all();

        return in_array($meeting->user_id, $userIds, true);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if ($user->id === $meeting->user_id) {
            return true;
        }

        if ($user->can('meetings.update')) {
            return true;
        }

        $context = app(TokenContext::class);
        $structure = app(StructureService::class);
        $userIds = $structure->listUsers($context)->pluck('id')->all();

        return in_array($meeting->user_id, $userIds, true);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $user->can('meetings.delete');
    }
}
