<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmEmployee extends Model
{
    protected $fillable = [
        'client_id',
        'name',
        'pesel',
        'contract_type',
        'benefit_amount',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'client_id');
    }
}
