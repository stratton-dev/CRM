<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmClientProfile extends Model
{
    protected $fillable = [
        'client_id',
        'owner_user_id',
        'status',
        'contact_name',
        'contact_phone',
        'contact_email',
        'employees_total',
        'employees_uop',
        'employees_uz',
        'avg_wage_uop',
        'avg_wage_uz',
        'service_fee_percent',
        'offer_sent_date',
        'contract_signed_date',
        'reservation_end_date',
        'analysis_json',
        'source',
        'industry',
        'company_size',
        'contact_position',
        'is_decision_maker',
    ];

    protected $casts = [
        'offer_sent_date' => 'date',
        'contract_signed_date' => 'date',
        'reservation_end_date' => 'date',
        'analysis_json' => 'array',
        'is_decision_maker' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'client_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
