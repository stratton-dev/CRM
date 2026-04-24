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
        'file_path',
        'file_name',
        'file_type',
        'file_size',
    ];

    protected $casts = [
        'required' => 'boolean',
    ];

    protected $appends = [
        'file_url',
    ];

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) return null;
        try {
            return route('consents.file', $this);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function clientConsents(): HasMany
    {
        return $this->hasMany(ClientConsent::class);
    }
}
