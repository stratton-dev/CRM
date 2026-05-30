<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmCommissionChainOverride extends Model
{
    protected $table = 'crm_commission_chain_overrides';

    protected $fillable = [
        'sub_member_supabase_id',
        'ancestor_supabase_id',
        'rate',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
    ];
}
