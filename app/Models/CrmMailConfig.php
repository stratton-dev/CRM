<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmMailConfig extends Model
{
    protected $fillable = [
        'user_id',
        'from_name',
        'from_email',
        'imap_host',
        'imap_port',
        'imap_secure',
        'imap_username',
        'imap_password',
        'imap_inbox_folder',
        'imap_sent_folder',
        'imap_trash_folder',
        'smtp_host',
        'smtp_port',
        'smtp_secure',
        'smtp_username',
        'smtp_password',
    ];

    protected $casts = [
        'imap_secure' => 'boolean',
        'smtp_secure' => 'boolean',
        'imap_password' => 'encrypted',
        'smtp_password' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
