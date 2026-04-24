<?php

namespace App\Events\Broadcast;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReverbBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<int, string> $channels
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $name,
        public array $channels,
        public array $payload = []
    ) {
    }

    public function broadcastOn(): array
    {
        $channels = array_values(array_unique(array_filter($this->channels)));

        return array_map(
            fn (string $channel) => new PrivateChannel($channel),
            $channels
        );
    }

    public function broadcastAs(): string
    {
        return $this->name;
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
