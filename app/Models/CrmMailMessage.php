<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmMailMessage extends Model
{
    protected $fillable = [
        'user_id',
        'folder_key',
        'folder_path',
        'uid',
        'message_id',
        'from_name',
        'from_email',
        'to_email',
        'subject',
        'body_html',
        'body_text',
        'attachments',
        'sent_at',
        'read',
        'read_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'sent_at' => 'datetime',
        'read' => 'boolean',
        'read_at' => 'datetime',
    ];
}
