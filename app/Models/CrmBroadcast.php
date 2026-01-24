<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmBroadcast extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'event_key',
        'description',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function targets(): HasMany
    {
        return $this->hasMany(CrmBroadcastTarget::class, 'broadcast_id');
    }
}
