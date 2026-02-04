<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmMailJob extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'payload',
        'status',
        'attempts',
        'last_error',
        'scheduled_at',
        'locked_at',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'scheduled_at' => 'datetime',
        'locked_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
