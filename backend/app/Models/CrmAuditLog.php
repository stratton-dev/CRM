<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmAuditLog extends Model
{
    protected $fillable = [
        'actor_user_id',
        'action',
        'target_id',
        'details',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
