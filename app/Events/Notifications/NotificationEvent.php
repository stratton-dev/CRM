<?php

namespace App\Events\Notifications;

use App\Contracts\BroadcastsToReverb;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class NotificationEvent implements BroadcastsToReverb
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Notification $notification,
        public User $user
    ) {
    }

    public function broadcastChannels(): array
    {
        $supabaseId = $this->user->supabase_id ?: (string) $this->user->id;
        if ($supabaseId === '') {
            return [];
        }

        return ['user.'.$supabaseId];
    }

    public function broadcastPayload(): array
    {
        return array_merge($this->basePayload(), ['action' => $this->action()]);
    }

    abstract protected function action(): string;

    /**
     * @return array<string, mixed>
     */
    protected function basePayload(): array
    {
        return [
            'notification' => [
                'id' => (string) $this->notification->id,
                'userId' => (string) $this->notification->user_id,
                'type' => $this->notification->type,
                'title' => $this->notification->title,
                'body' => $this->notification->body,
                'readAt' => $this->notification->read_at?->toISOString(),
            ],
            'userSupabaseId' => $this->user->supabase_id,
        ];
    }
}
