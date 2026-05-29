<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmOfferPdf extends Model
{
    protected $table = 'crm_offer_pdfs';

    protected $fillable = [
        'client_id',
        'user_id',
        'calculation_id',
        'name',
        'mime_type',
        'size_bytes',
        'valid_until',
        'pdf_base64',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'size_bytes' => 'integer',
        'calculation_id' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
