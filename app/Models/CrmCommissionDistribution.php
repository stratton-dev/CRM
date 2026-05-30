<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmCommissionDistribution extends Model
{
    protected $table = 'crm_commission_distributions';

    protected $fillable = [
        'source_user_supabase_id',
        'base_amount',
        'period',
        'source',
        'source_reference',
        'note',
        'created_by_user_id',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CrmCommissionDistributionItem::class, 'distribution_id');
    }
}
