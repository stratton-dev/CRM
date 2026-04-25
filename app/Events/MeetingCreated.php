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

        $supabaseId = $user?->supabase_id ?: (string) $user?->id;
        if ($supabaseId) {
            $channels[] = 'user.'.$supabaseId;
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
                'userSupabaseId' => $this->meeting->user?->supabase_id,
                'status' => $this->meeting->status,
                'calculationShown' => (bool) $this->meeting->calculation_shown,
                'validUntil' => $this->meeting->valid_until?->toDateString(),
            ],
        ];
    }
}
