<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'user_id',
        'status',
        'calculation_shown',
        'offer_status',
        'valid_until',
        'paused_at',
        'resume_at',
    ];

    protected $casts = [
        'calculation_shown' => 'boolean',
        'valid_until' => 'date',
        'paused_at' => 'datetime',
        'resume_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function analysis(): HasOne
    {
        return $this->hasOne(MeetingAnalysis::class);
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(Calculation::class);
    }

    public function offer(): HasOne
    {
        return $this->hasOne(Offer::class);
    }
}
