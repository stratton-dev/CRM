<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmDashboardCalculation extends Model
{
    protected $fillable = [
        'user_id',
        'company',
        'nip',
        'meeting_id',
        'calculation_date',
        'valid_until',
        'status',
    ];

    protected $casts = [
        'calculation_date' => 'date',
        'valid_until' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
