<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\CrmClientProfile;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'added_by_user_id',
        'name','nip','regon','krs','address_json',
        'address_line1','address_line2','postal_code','city','country',
        'email','phone','website','notes',
        'industry','vat_type','employee_count','benefits_enabled',
    ];

    protected $casts = [
        'address_json' => 'array',
        'employee_count' => 'integer',
        'benefits_enabled' => 'boolean',
    ];

    public function addedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'added_by_user_id');
    }

    public function notes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ClientNote::class);
    }

    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

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

    public function crmProfile(): HasOne
    {
        return $this->hasOne(CrmClientProfile::class, 'client_id');
    }

    protected static function booted()
    {
        static::deleting(function ($company) {
            // Because Client extends Company for the same table, Eloquent might guess foreign key based on the instance class name.
            // If $company is an instance of Client (which extends Company), the relation guesser might look for client_id on employees table.
            // But employees table has company_id.
            // So we must be explicit about the deletion using the base query or explicit foreign key relations.

            // Employees (foreign key: company_id)
            \App\Models\Employee::where('company_id', $company->id)->delete();

            // Offers (foreign key: company_id)
            \App\Models\Offer::where('company_id', $company->id)->delete();

            // Payroll Calculations (foreign key: company_id)
            \App\Models\PayrollCalculation::where('company_id', $company->id)->delete();

            // Relations that use client_id (mostly strictly Client related)
            // Meetings
            \App\Models\Meeting::where('client_id', $company->id)->delete();
            // Payrolls
            \App\Models\Payroll::where('client_id', $company->id)->delete();
            // Documents
            \App\Models\Document::where('client_id', $company->id)->delete();

            // Client Contacts
            \App\Models\ClientContact::where('client_id', $company->id)->delete();

            // Client Consents
            \App\Models\ClientConsent::where('client_id', $company->id)->delete();

            // CRM Profile
            \App\Models\CrmClientProfile::where('client_id', $company->id)->delete();

            // CRM Activities
            \App\Models\CrmClientActivity::where('client_id', $company->id)->delete();
        });
    }
}
