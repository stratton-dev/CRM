<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientConsent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'consent_id',
        'accepted_at',
        'denied_at',
        'source',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'denied_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(Consent::class);
    }
}
