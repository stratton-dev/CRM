<?php

namespace App\Events\Structure;

class StructureUserCreated extends StructureUserEvent
{
    public function broadcastName(): string
    {
        return 'structure.user.created';
    }

    public function broadcastPayload(): array
    {
        return $this->payloadWithAction('created');
    }
}
