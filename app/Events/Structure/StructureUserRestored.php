<?php

namespace App\Events\Structure;

class StructureUserRestored extends StructureUserEvent
{
    public function broadcastName(): string
    {
        return 'structure.user.restored';
    }

    public function broadcastPayload(): array
    {
        return $this->payloadWithAction('restored');
    }
}
