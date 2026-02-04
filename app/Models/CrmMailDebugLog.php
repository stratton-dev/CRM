<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmMailDebugLog extends Model
{
    protected $fillable = [
        'user_id',
        'level',
        'message',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
