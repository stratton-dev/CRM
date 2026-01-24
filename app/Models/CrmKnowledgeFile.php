<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmKnowledgeFile extends Model
{
    protected $fillable = [
        'name',
        'description',
        'category',
        'file_type',
        'file_url',
        'size',
        'added_at',
    ];

    protected $casts = [
        'added_at' => 'datetime',
    ];
}
