<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmMailSyncStat extends Model
{
    protected $fillable = [
        'user_id',
        'last_sync_at',
        'last_duration_ms',
        'last_status',
        'last_error',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'last_duration_ms' => 'integer',
    ];
}
