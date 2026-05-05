<?php

namespace App\Services\Ai;

use App\Models\AiChatFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    private const MAX_BYTES = 20 * 1024 * 1024; // 20 MB

    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'text/markdown',
        'text/x-markdown',
    ];

    public function store(UploadedFile $file, int $userId, ?int $conversationId = null): AiChatFile
    {
        $mime = $file->getMimeType() ?? $file->getClientMimeType();

        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Plik jest za duży. Maksymalny rozmiar to 20 MB.');
        }

        // Normalise .md mime — PHP often returns text/plain for .md files
        if ($mime === 'text/plain' && str_ends_with(strtolower($file->getClientOriginalName()), '.md')) {
            $mime = 'text/markdown';
        }

        if (!in_array($mime, self::ALLOWED_MIMES)) {
            throw new \InvalidArgumentException(
                'Nieobsługiwany typ pliku. Dozwolone formaty: PDF, DOC, DOCX, TXT, MD.'
            );
        }

        $uuid      = Str::uuid();
        $ext       = $file->getClientOriginalExtension() ?: 'bin';
        $path      = "ai-uploads/{$userId}/{$uuid}.{$ext}";

        Storage::disk('local')->putFileAs(
            "ai-uploads/{$userId}",
            $file,
            "{$uuid}.{$ext}"
        );

        return AiChatFile::create([
            'user_id'         => $userId,
            'conversation_id' => $conversationId,
            'original_name'   => $file->getClientOriginalName(),
            'stored_path'     => $path,
            'mime_type'       => $mime,
            'size_bytes'      => $file->getSize(),
            'direction'       => 'upload',
            'expires_at'      => now()->addHours(24),
        ]);
    }

    public function storeRaw(string $content, string $filename, string $mimeType, int $userId, ?int $conversationId = null): AiChatFile
    {
        $uuid = Str::uuid();
        $ext  = pathinfo($filename, PATHINFO_EXTENSION) ?: 'bin';
        $path = "ai-uploads/{$userId}/{$uuid}.{$ext}";

        Storage::disk('local')->put($path, $content);

        return AiChatFile::create([
            'user_id'         => $userId,
            'conversation_id' => $conversationId,
            'original_name'   => $filename,
            'stored_path'     => $path,
            'mime_type'       => $mimeType,
            'size_bytes'      => strlen($content),
            'direction'       => 'generated',
            'expires_at'      => now()->addHours(24),
        ]);
    }
}
