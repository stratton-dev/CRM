<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSpreadsheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'user_id',
        'original_filename',
        'storage_path',
        'status',
        'result_pdf_path',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class); // Assuming User model is App\Models\User
    }
}
