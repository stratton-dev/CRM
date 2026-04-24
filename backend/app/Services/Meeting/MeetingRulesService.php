<?php

namespace App\Services\Meeting;

use App\Models\Meeting;
use DomainException;

class MeetingRulesService
{
    public function ensureActive(Meeting $meeting): void
    {
        if ($meeting->status !== 'open') {
            throw new DomainException('Meeting is not active.');
        }
    }

    public function ensureNotExpired(Meeting $meeting): void
    {
        if ($meeting->valid_until && $meeting->valid_until->isPast()) {
            throw new DomainException('Meeting has expired.');
        }
    }
}
