<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key',
        'actor_supabase_id',
        'request_hash',
        'response_body',
        'status',
        'created_at',
    ];

    protected $casts = [
        'response_body' => 'array',
        'created_at' => 'datetime',
    ];
}
