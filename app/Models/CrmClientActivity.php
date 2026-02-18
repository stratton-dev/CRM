<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmClientActivity extends Model
{
    protected $fillable = [
        'client_id',
        'user_id',
        'type',
        'description',
        'is_completed',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'is_completed' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
