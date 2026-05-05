<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatFile extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
        'original_name',
        'stored_path',
        'mime_type',
        'size_bytes',
        'direction',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
