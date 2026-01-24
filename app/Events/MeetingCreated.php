<?php

namespace App\Events;

use App\Contracts\BroadcastsToReverb;
use App\Models\Meeting;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MeetingCreated implements BroadcastsToReverb
{
    use Dispatchable, SerializesModels;

    public function __construct(public Meeting $meeting)
    {
    }

    public function broadcastChannels(): array
    {
        $channels = [];
        $user = $this->meeting->user;

        if ($user?->team_id) {
            $channels[] = 'team.'.$user->team_id;
        }

        $keycloakId = $user?->keycloak_id ?: (string) $user?->id;
        if ($keycloakId) {
            $channels[] = 'user.'.$keycloakId;
        }

        return $channels;
    }

    public function broadcastName(): string
    {
        return 'meetings.created';
    }

    public function broadcastPayload(): array
    {
        return [
            'action' => 'created',
            'meeting' => [
                'id' => (string) $this->meeting->id,
                'clientId' => (string) $this->meeting->client_id,
                'userId' => (string) $this->meeting->user_id,
                'userKeycloakId' => $this->meeting->user?->keycloak_id,
                'status' => $this->meeting->status,
                'calculationShown' => (bool) $this->meeting->calculation_shown,
                'validUntil' => $this->meeting->valid_until?->toDateString(),
            ],
        ];
    }
}
