<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CrmStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'description',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(CrmEvent::class, 'crm_status_event_links', 'status_id', 'event_id')
            ->withTimestamps();
    }
}
