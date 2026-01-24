<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmCommissionConfig extends Model
{
    protected $fillable = [
        'sales_commission_first_month_lt14',
        'sales_commission_first_month_gt14',
        'sales_commission_renewal',
    ];
}
