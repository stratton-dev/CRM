<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmDashboardNews extends Model
{
    protected $fillable = [
        'tag',
        'title',
        'description',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];
}
