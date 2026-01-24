<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CrmClientProfile;

class Client extends Company
{
    protected $table = 'companies';

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class, 'client_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(ClientConsent::class, 'client_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'client_id');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'client_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'client_id');
    }

    public function crmProfile()
    {
        return $this->hasOne(CrmClientProfile::class, 'client_id');
    }
}
