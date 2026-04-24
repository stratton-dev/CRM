<?php

namespace App\Events\Structure;

class StructureUserMoved extends StructureUserEvent
{
    public function broadcastName(): string
    {
        return 'structure.user.moved';
    }

    public function broadcastPayload(): array
    {
        return $this->payloadWithAction('moved');
    }
}
