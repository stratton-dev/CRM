<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'nip',
        'regon',
        'krs',
        'address_json',
        'gus_synced_at',
    ];

    protected $casts = [
        'address_json' => 'array',
        'gus_synced_at' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'organization_id');
    }
}
