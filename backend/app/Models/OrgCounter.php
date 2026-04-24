<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgCounter extends Model
{
    protected $fillable = [
        'scope',
        'next_number',
    ];
}
