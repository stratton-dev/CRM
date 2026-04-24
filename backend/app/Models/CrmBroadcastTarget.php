<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmBroadcastTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'broadcast_id',
        'target_type',
        'target_value',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(CrmBroadcast::class, 'broadcast_id');
    }
}
