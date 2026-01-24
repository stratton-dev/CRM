<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Calculation extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'employee_count',
        'savings_amount',
        'valid_until',
        'status',
    ];

    protected $casts = [
        'employee_count' => 'integer',
        'savings_amount' => 'integer',
        'valid_until' => 'date',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
