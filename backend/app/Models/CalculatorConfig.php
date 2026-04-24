<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculatorConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'scope','scope_id','key','version','value_json',
        'effective_from','effective_to','is_active','created_by','notes',
    ];

    protected $casts = [
        'value_json' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
