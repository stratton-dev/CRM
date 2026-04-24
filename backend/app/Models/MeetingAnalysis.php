<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAnalysis extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'meeting_analysis';

    protected $fillable = [
        'meeting_id',
        'industry',
        'tax_model',
        'zus_cost_level',
        'investments_planned',
        'expected_savings',
        'debt_level',
        'benefits',
        'project_participation',
        'past_savings',
        'current_savings',
        'planned_investments',
        'declared_savings',
        'debts',
        'vat_model',
    ];

    protected $casts = [
        'zus_cost_level' => 'integer',
        'investments_planned' => 'boolean',
        'expected_savings' => 'integer',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
