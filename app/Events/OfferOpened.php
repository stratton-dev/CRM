<?php

namespace App\Events;

use App\Contracts\BroadcastsToReverb;
use App\Models\Offer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OfferOpened implements BroadcastsToReverb
{
    use Dispatchable, SerializesModels;

    public function __construct(public Offer $offer)
    {
    }

    public function broadcastChannels(): array
    {
        $channels = [];
        $user = $this->offer->meeting?->user;

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
        return 'offers.opened';
    }

    public function broadcastPayload(): array
    {
        return [
            'action' => 'opened',
            'offer' => [
                'id' => (string) $this->offer->id,
                'companyId' => (string) $this->offer->company_id,
                'meetingId' => $this->offer->meeting_id ? (string) $this->offer->meeting_id : null,
                'status' => $this->offer->status,
                'openedAt' => $this->offer->opened_at?->toISOString(),
                'expiresAt' => $this->offer->expires_at?->toDateString(),
            ],
        ];
    }
}
