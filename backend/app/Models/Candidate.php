<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'first_name',
        'last_name',
        'company_name',
        'nip',
        'regon',
        'krs',
        'pesel',
        'email',
        'phone',
        'address_json',
        'status',
        'manager_id',
    ];

    protected $casts = [
        'address_json' => 'array',
    ];

    public function documents()
    {
        return $this->hasMany(CandidateDocument::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
