<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmEventLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_key',
        'status_id',
        'user_id',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(CrmEvent::class, 'event_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(CrmStatus::class, 'status_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
