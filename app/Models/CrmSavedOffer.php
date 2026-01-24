<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmSavedOffer extends Model
{
    protected $fillable = [
        'client_id',
        'name',
        'employees_uop',
        'avg_wage_uop',
        'employees_uz',
        'estimated_savings',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'client_id');
    }
}
