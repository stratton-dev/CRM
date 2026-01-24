<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmTeamCommissionThreshold extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_group_path',
        'renewal_commission_rate',
        'override_commission_rate',
    ];

    protected $casts = [
        'renewal_commission_rate' => 'decimal:4',
        'override_commission_rate' => 'decimal:4',
    ];
}
