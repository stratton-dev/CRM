<?php

namespace App\Contracts;

interface BroadcastsToReverb
{
    /**
     * @return array<int, string>
     */
    public function broadcastChannels(): array;

    public function broadcastName(): string;

    /**
     * @return array<string, mixed>
     */
    public function broadcastPayload(): array;
}
