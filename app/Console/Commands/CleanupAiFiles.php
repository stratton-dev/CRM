<?php

namespace App\Console\Commands;

use App\Models\AiChatFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupAiFiles extends Command
{
    protected $signature   = 'ai:cleanup-files';
    protected $description = 'Delete expired ai_chat_files from disk and database';

    public function handle(): void
    {
        $expired = AiChatFile::where('expires_at', '<', now())->get();

        foreach ($expired as $file) {
            Storage::disk('local')->delete($file->stored_path);
        }

        $count = AiChatFile::where('expires_at', '<', now())->delete();
        $this->info("Deleted {$count} expired AI chat files.");
    }
}
