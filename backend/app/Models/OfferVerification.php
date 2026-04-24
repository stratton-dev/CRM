<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferVerification extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'offer_id',
        'type',
        'code',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
