<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBaseDocument extends Model
{
    protected $fillable = [
        'title', 'original_filename', 'mime_type', 'file_size',
        'status', 'chunks_count', 'error_message', 'uploaded_by',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeBaseChunk::class, 'document_id');
    }
}
