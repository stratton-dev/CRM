<?php

namespace App\Services\Crm;

use App\Models\CrmMailConfig;
use RuntimeException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Cache;

class CrmMailboxService
{
    public function listMessages(CrmMailConfig $config, string $folderKey, int $limit = 50, int $offset = 0): array
    {
        $payload = [
            'action' => 'list',
            'config' => $this->imapConfig($config),
            'params' => [
                'folderKey' => $folderKey,
                'folderName' => $this->folderName($config, $folderKey),
                'limit' => $limit,
                'offset' => $offset,
            ],
        ];

        $result = $this->runNode($payload);
        $data = $result['data'] ?? [];
        if (isset($data['meta'])) {
            Log::channel('mail')->info('IMAP list meta', [
                'folder' => $folderKey,
                'meta' => $data['meta'],
            ]);
        }
        return $data;
    }

    public function listFolders(CrmMailConfig $config): array
    {
        $payload = [
            'action' => 'folders',
            'config' => $this->imapConfig($config),
        ];

        $result = $this->runNode($payload);
        return $result['data'] ?? [];
    }

    public function listFoldersDiagnostics(CrmMailConfig $config): array
    {
        $payload = [
            'action' => 'folders',
            'config' => $this->imapConfig($config),
            'params' => [
                'diagnostics' => true,
            ],
        ];

        $result = $this->runNode($payload);
        $data = $result['data'] ?? [];
        return is_array($data) ? $data : ['folders' => [], 'meta' => []];
    }

    public function listFoldersCached(CrmMailConfig $config, int $userId): array
    {
        $ttl = (int) env('IMAP_FOLDERS_CACHE_TTL', 600);
        if ($ttl <= 0) {
            return $this->listFolders($config);
        }
        $cacheKey = "imap_folders_user_{$userId}";
        return Cache::remember($cacheKey, $ttl, function () use ($config) {
            return $this->listFolders($config);
        });
    }

    public function testConnection(CrmMailConfig $config): void
    {
        $payload = [
            'action' => 'test',
            'config' => $this->imapConfig($config),
        ];

        $this->runNode($payload);
    }

    public function markRead(CrmMailConfig $config, string $folderKey, int $uid): void
    {
        $payload = [
            'action' => 'markRead',
            'config' => $this->imapConfig($config),
            'params' => [
                'folderName' => $this->folderName($config, $folderKey),
                'uid' => $uid,
            ],
        ];

        $this->runNode($payload);
    }

    public function getMessageBody(CrmMailConfig $config, string $folderKey, int|string $uid): array
    {
        $payload = [
            'action' => 'getBody',
            'config' => $this->imapConfig($config),
            'params' => [
                'folderKey' => $folderKey,
                'folderName' => $this->folderName($config, $folderKey),
                'uid' => $uid,
            ],
        ];

        $result = $this->runNode($payload);
        return $result['data'] ?? [];
    }

    public function sendMessage(CrmMailConfig $config, array $message): array
    {
        $payload = [
            'action' => 'send',
            'config' => [
                'imap' => $this->imapConfig($config),
                'smtp' => $this->smtpConfig($config),
                'folderName' => $this->folderName($config, 'SENT'),
            ],
            'params' => $message,
        ];

        $result = $this->runNode($payload);
        return $result['data'] ?? [];
    }

    public function saveDraft(CrmMailConfig $config, array $message): array
    {
        $payload = [
            'action' => 'saveDraft',
            'config' => [
                'imap' => $this->imapConfig($config),
                'smtp' => $this->smtpConfig($config),
            ],
            'params' => array_merge($message, [
                'folderName' => $this->folderName($config, 'DRAFTS'),
            ]),
        ];

        $result = $this->runNode($payload);
        return $result['data'] ?? [];
    }

    public function moveMessage(CrmMailConfig $config, string $fromFolderKey, int $uid, string $toFolderKey): array
    {
        $payload = [
            'action' => 'moveMessage',
            'config' => $this->imapConfig($config),
            'params' => [
                'fromFolder' => $this->folderName($config, $fromFolderKey),
                'toFolder'   => $this->folderName($config, $toFolderKey),
                'uid'        => $uid,
            ],
        ];

        $result = $this->runNode($payload);
        return $result['data'] ?? [];
    }

    private function folderName(CrmMailConfig $config, string $folderKey): string
    {
        return match (strtoupper($folderKey)) {
            'SENT'   => $config->imap_sent_folder,
            'TRASH'  => $config->imap_trash_folder,
            'DRAFTS' => $config->imap_drafts_folder ?? 'Drafts',
            'SPAM'   => $config->imap_spam_folder ?? 'Junk',
            default  => $config->imap_inbox_folder,
        };
    }

    private function imapConfig(CrmMailConfig $config): array
    {
        try {
            $pass = $config->imap_password;
        } catch (\Throwable) {
            throw new \RuntimeException('Hasło IMAP jest nieprawidłowe lub wymaga ponownego ustawienia. Przejdź do Ustawień poczty i zapisz hasło ponownie.');
        }
        return [
            'host' => $config->imap_host,
            'port' => $config->imap_port,
            'secure' => (bool) $config->imap_secure,
            'auth' => [
                'user' => $config->imap_username,
                'pass' => $pass,
            ],
        ];
    }

    private function smtpConfig(CrmMailConfig $config): array
    {
        try {
            $smtpPass = $config->smtp_password;
        } catch (\Throwable) {
            throw new \RuntimeException('Hasło SMTP jest nieprawidłowe lub wymaga ponownego ustawienia. Przejdź do Ustawień poczty i zapisz hasło ponownie.');
        }
        return [
            'host' => $config->smtp_host,
            'port' => $config->smtp_port,
            'secure' => (bool) $config->smtp_secure,
            'auth' => [
                'user' => $config->smtp_username,
                'pass' => $smtpPass,
            ],
            'from' => [
                'name' => $config->from_name,
                'email' => $config->from_email,
            ],
        ];
    }

    private function runNode(array $payload): array
    {
        $script = base_path('scripts/imapflow-mailbox.mjs');
        if (!file_exists($script)) {
            throw new RuntimeException('IMAP helper script not found.');
        }

        $debug = env('IMAP_DEBUG', false);
        if ($debug) {
            $imap = $payload['config'] ?? ($payload['config']['imap'] ?? null);
            $imapUser = is_array($imap) ? ($imap['auth']['user'] ?? null) : null;
            $imapPass = is_array($imap) ? ($imap['auth']['pass'] ?? null) : null;
            Log::channel('mail')->info('IMAP helper start', [
                'action' => $payload['action'] ?? null,
                'host' => is_array($imap) ? ($imap['host'] ?? null) : null,
                'port' => is_array($imap) ? ($imap['port'] ?? null) : null,
                'secure' => is_array($imap) ? ($imap['secure'] ?? null) : null,
                'user' => is_string($imapUser) ? substr($imapUser, 0, 2).'***'.substr($imapUser, -2) : null,
                'pass_set' => is_array($imap) ? isset($imap['auth']['pass']) : null,
                'pass_len' => is_string($imapPass) ? strlen($imapPass) : null,
            ]);
        }

        $process = new Process(['node', '--no-warnings', $script]);
        $process->setInput(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $timeoutSeconds = (int) env('IMAP_PROCESS_TIMEOUT', 60);
        $process->setTimeout($timeoutSeconds > 0 ? $timeoutSeconds : null);
        $process->run();

        if (!$process->isSuccessful()) {
            $stderr = trim($process->getErrorOutput());
            $stdout = trim($process->getOutput());
            Log::warning('IMAP helper failed', [
                'action' => $payload['action'] ?? null,
                'stdout' => $stdout,
                'stderr' => $stderr,
            ]);
            Log::channel('mail')->warning('IMAP helper failed', [
                'action' => $payload['action'] ?? null,
                'stdout' => $stdout,
                'stderr' => $stderr,
            ]);
            throw new RuntimeException($stderr ?: $stdout ?: 'IMAP helper failed.');
        }

        $output = trim($process->getOutput());
        if ($debug && ($payload['action'] ?? null) === 'list') {
            Log::channel('mail')->info('IMAP helper raw output (list)', [
                'output' => $output,
            ]);
        }
        if (strpos($output, '"error"') !== false) {
            if (preg_match('/\\{\"error\"\\s*:\\s*\".*?\"\\}/s', $output, $match)) {
                $errorPayload = json_decode($match[0], true);
                if (is_array($errorPayload) && !empty($errorPayload['error'])) {
                    Log::channel('mail')->warning('IMAP helper returned error', [
                        'action' => $payload['action'] ?? null,
                        'error' => $errorPayload['error'],
                    ]);
                    throw new RuntimeException((string) $errorPayload['error']);
                }
            }
        }

        $decoded = json_decode($output, true);
        if (!is_array($decoded)) {
            $fallback = null;
            $start = strrpos($output, '{');
            if ($start !== false) {
                $fallback = json_decode(substr($output, $start), true);
            }
            if (is_array($fallback)) {
                $decoded = $fallback;
            } else {
                Log::warning('IMAP helper invalid response', [
                    'action' => $payload['action'] ?? null,
                    'stdout' => $output,
                ]);
                Log::channel('mail')->warning('IMAP helper invalid response', [
                    'action' => $payload['action'] ?? null,
                    'stdout' => $output,
                ]);
                throw new RuntimeException('Invalid IMAP helper response.');
            }
        }
        if (!empty($decoded['error'])) {
            Log::warning('IMAP helper returned error', [
                'action' => $payload['action'] ?? null,
                'error' => $decoded['error'],
            ]);
            Log::channel('mail')->warning('IMAP helper returned error', [
                'action' => $payload['action'] ?? null,
                'error' => $decoded['error'],
            ]);
            throw new RuntimeException((string) $decoded['error']);
        }

        if ($debug) {
            Log::channel('mail')->info('IMAP helper ok', [
                'action' => $payload['action'] ?? null,
            ]);
        }

        return $decoded;
    }
}
