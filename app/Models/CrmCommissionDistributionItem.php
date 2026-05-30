<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmCommissionDistributionItem extends Model
{
    protected $table = 'crm_commission_distribution_items';

    protected $fillable = [
        'distribution_id',
        'receiver_user_supabase_id',
        'receiver_role_at_time',
        'level',
        'rate',
        'amount',
    ];

    protected $casts = [
        'level' => 'integer',
        'rate' => 'decimal:4',
        'amount' => 'decimal:2',
    ];

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(CrmCommissionDistribution::class, 'distribution_id');
    }
}
