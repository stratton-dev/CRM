<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmDashboardKpi extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'value',
        'score',
        'min_target',
        'subtitle',
        'missing',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
