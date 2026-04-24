<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'type',
        'file_path',
        'autenti_process_id',
        'autenti_status',
        'autenti_file_id',
        'autenti_last_event_at',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'autenti_last_event_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
