<?php

namespace App\Events\Structure;

class StructureUserRemoved extends StructureUserEvent
{
    public function broadcastName(): string
    {
        return 'structure.user.removed';
    }

    public function broadcastPayload(): array
    {
        return $this->payloadWithAction('removed');
    }
}
