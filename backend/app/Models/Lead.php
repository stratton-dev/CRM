<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'nip',
        'city',
        'postal_code',
        'street',
        'contact_person',
        'phone',
        'email',
        'notes',
        'user_id',
        'status',
        'source',
        'last_contact_at'
    ];

    protected $casts = [
        'last_contact_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
