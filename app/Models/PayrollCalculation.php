<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollCalculation extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','employee_id','offer_id','created_by',
        'period_year','period_month',
        'engine_version','config_version','source',
        'inputs_json','outputs_json','tags','notes',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'inputs_json' => 'array',
        'outputs_json' => 'array',
        'tags' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
