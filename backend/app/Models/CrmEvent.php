<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CrmEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function statuses(): BelongsToMany
    {
        return $this->belongsToMany(CrmStatus::class, 'crm_status_event_links', 'event_id', 'status_id')
            ->withTimestamps();
    }
}
