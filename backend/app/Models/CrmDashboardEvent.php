<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmDashboardEvent extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'start_at',
    ];

    protected $casts = [
        'start_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
