<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consent extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'code',
        'title',
        'description',
        'required',
    ];

    protected $casts = [
        'required' => 'boolean',
    ];

    public function clientConsents(): HasMany
    {
        return $this->hasMany(ClientConsent::class);
    }
}
