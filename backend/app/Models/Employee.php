<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'first_name','last_name','email','phone',
        'birth_date','age','gender',
        'contract_type','zus_type','kup','kup_percent','tax_free_amount','kzp',
        'net_total','net_cash',
        'active','notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'age' => 'integer',
        'kup' => 'integer',
        'kup_percent' => 'decimal:4',
        'tax_free_amount' => 'integer',
        'kzp' => 'boolean',
        'net_total' => 'decimal:2',
        'net_cash' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payrollCalculations(): HasMany
    {
        return $this->hasMany(PayrollCalculation::class);
    }
}
