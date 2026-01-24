<?php

namespace App\Actions\Meeting;

use App\Events\MeetingCreated;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Client\ClientValidationService;

class CreateMeetingAction
{
    public function __construct(private ClientValidationService $clientValidationService)
    {
    }

    public function execute(Client $client, User $user): Meeting
    {
        $this->clientValidationService->ensureRequiredConsents($client);

        $meeting = Meeting::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'valid_until' => now()->addDays(90),
        ]);

        $meeting->load('user');
        event(new MeetingCreated($meeting));

        return $meeting;
    }
}
