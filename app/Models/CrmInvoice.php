<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmInvoice extends Model
{
    protected $fillable = [
        'number',
        'client_id',
        'issue_date',
        'amount_net',
        'amount_gross',
        'service_fee_net',
        'status',
        'pdf_url',
    ];

    protected $casts = [
        'issue_date' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'client_id');
    }
}
