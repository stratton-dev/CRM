<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMemory extends Model
{
    protected $fillable = [
        'user_id',
        'content',
        'memory_type',
        'related_client_id',
        'importance',
        'last_accessed_at',
        'access_count',
        'source_conversation_id',
    ];

    protected $casts = [
        'importance'       => 'float',
        'access_count'     => 'integer',
        'last_accessed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceConversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'source_conversation_id');
    }

    /**
     * Etykieta czytelna dla typu pamięci.
     */
    public function typeLabel(): string
    {
        return match ($this->memory_type) {
            'client_fact' => 'Fakt o kliencie',
            'preference'  => 'Preferencja',
            'decision'    => 'Decyzja/Zadanie',
            'context'     => 'Kontekst',
            default       => 'Pamięć',
        };
    }
}
