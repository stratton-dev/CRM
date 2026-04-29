<?php

namespace App\Events;

use App\Contracts\BroadcastsToReverb;
use App\Models\ChatMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements BroadcastsToReverb
{
    use Dispatchable, SerializesModels;

    public function __construct(public ChatMessage $message)
    {
    }

    public function broadcastChannels(): array
    {
        return ['chat.'.$this->message->conversation_id];
    }

    public function broadcastName(): string
    {
        return 'chat.message';
    }

    public function broadcastPayload(): array
    {
        $sender = $this->message->sender;

        return [
            'id'              => $this->message->id,
            'conversationId'  => $this->message->conversation_id,
            'senderId'        => $this->message->sender_id,
            'senderName'      => $sender?->name ?? 'Unknown',
            'body'            => $this->message->body,
            'type'            => $this->message->type,
            'createdAt'       => $this->message->created_at->toIso8601String(),
        ];
    }
}
