<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name','nip','regon','krs',
        'address_line1','address_line2','postal_code','city','country',
        'email','phone','website','notes',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function payrollCalculations(): HasMany
    {
        return $this->hasMany(PayrollCalculation::class);
    }
}
