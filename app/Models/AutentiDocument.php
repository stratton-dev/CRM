<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutentiDocument extends Model
{
    protected $fillable = [
        'user_id',
        'user_keycloak_id',
        'initiator_keycloak_id',
        'recipient_name',
        'recipient_email',
        'document_list',
        'status',
        'sent_at',
        'viewed_at',
        'signed_at',
        'autenti_process_id',
        'autenti_status',
        'autenti_last_event_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'signed_at' => 'datetime',
        'autenti_last_event_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
