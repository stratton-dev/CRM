<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'position',
        'phone',
        'email',
        'is_decision_maker',
    ];

    protected $casts = [
        'is_decision_maker' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
