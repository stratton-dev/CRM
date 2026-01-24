<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmViewPermission extends Model
{
    protected $fillable = [
        'view_key',
        'roles',
    ];

    protected $casts = [
        'roles' => 'array',
    ];
}
