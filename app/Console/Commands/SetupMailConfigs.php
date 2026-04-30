<?php

namespace App\Console\Commands;

use App\Models\CrmMailConfig;
use App\Models\User;
use Illuminate\Console\Command;

class SetupMailConfigs extends Command
{
    protected $signature = 'mail:setup-configs
                            {--force : Overwrite existing configs}';

    protected $description = 'Create/update IMAP/SMTP mail configs for CRM users';

    private const IMAP_HOST = 'serwer2577868.home.pl';
    private const IMAP_PORT = 993;
    private const SMTP_HOST = 'serwer2577868.home.pl';
    private const SMTP_PORT = 465;

    public function handle(): int
    {
        $configs = $this->loadConfigs();

        if (empty($configs)) {
            $this->error('No user configs provided. Set MAIL_SETUP_CONFIGS env var.');
            return 1;
        }

        foreach ($configs as $email => $password) {
            $user = User::where('email', $email)->first();
            if (!$user) {
                $this->warn("User not found: $email — skipping");
                continue;
            }

            $existing = CrmMailConfig::where('user_id', $user->id)->first();
            if ($existing && !$this->option('force')) {
                $this->line("Skipping $email (config exists, use --force to overwrite)");
                continue;
            }

            $data = [
                'user_id' => $user->id,
                'from_name' => $user->name,
                'from_email' => $email,
                'imap_host' => self::IMAP_HOST,
                'imap_port' => self::IMAP_PORT,
                'imap_secure' => true,
                'imap_username' => $email,
                'imap_password' => $password,
                'imap_inbox_folder' => 'INBOX',
                'imap_sent_folder' => 'Sent',
                'imap_trash_folder' => 'Trash',
                'smtp_host' => self::SMTP_HOST,
                'smtp_port' => self::SMTP_PORT,
                'smtp_secure' => true,
                'smtp_username' => $email,
                'smtp_password' => $password,
            ];

            if ($existing) {
                // Delete and recreate to avoid DecryptException from isDirty()
                // comparison when APP_KEY was rotated (old encrypted value can't be decrypted)
                $existing->delete();
                CrmMailConfig::create($data);
                $this->info("Updated: $email");
            } else {
                CrmMailConfig::create($data);
                $this->info("Created: $email");
            }
        }

        $this->info('Done.');
        return 0;
    }

    private function loadConfigs(): array
    {
        $raw = (string) env('MAIL_SETUP_CONFIGS', '');
        if ($raw === '') {
            return [];
        }
        $result = [];
        foreach (explode(',', $raw) as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) === 2) {
                $result[trim($parts[0])] = trim($parts[1]);
            }
        }
        return $result;
    }
}
