<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmMailFolder extends Model
{
    protected $fillable = [
        'user_id',
        'path',
        'name',
        'flags',
        'listed',
        'special_use',
        'uid_validity',
        'uid_next',
        'exists',
    ];

    protected $casts = [
        'flags' => 'array',
        'listed' => 'boolean',
        'uid_validity' => 'integer',
        'uid_next' => 'integer',
        'exists' => 'integer',
    ];
}
