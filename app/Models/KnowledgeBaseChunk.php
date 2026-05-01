<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseChunk extends Model
{
    protected $fillable = [
        'document_id', 'content', 'chunk_index', 'tokens_count', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseDocument::class, 'document_id');
    }
}
