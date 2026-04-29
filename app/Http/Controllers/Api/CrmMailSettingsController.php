<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmMailConfig;
use App\Services\Crm\CrmMailboxService;
use Illuminate\Http\Request;

class CrmMailSettingsController extends Controller
{
    public function __construct(private readonly CrmMailboxService $mailbox)
    {
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $config = CrmMailConfig::query()->where('user_id', $user->id)->first();

        if (!$config) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->formatConfig($config)]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $config = CrmMailConfig::query()->where('user_id', $user->id)->first();

        if (env('IMAP_DEBUG', false)) {
            \Log::channel('mail')->info('Mail settings update request', [
                'user_id' => $user->id,
                'has_config' => (bool) $config,
            ]);
        }

        $data = $request->validate([
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:255',
            'imap_host' => 'required|string|max:255',
            'imap_port' => 'required|integer|min:1|max:65535',
            'imap_secure' => 'required|boolean',
            'imap_username' => 'required|string|max:255',
            'imap_password' => ($config ? 'nullable' : 'required') . '|string|max:1024',
            'imap_inbox_folder' => 'nullable|string|max:255',
            'imap_sent_folder' => 'nullable|string|max:255',
            'imap_trash_folder' => 'nullable|string|max:255',
            'smtp_host' => 'required|string|max:255',
            'smtp_port' => 'required|integer|min:1|max:65535',
            'smtp_secure' => 'required|boolean',
            'smtp_username' => 'required|string|max:255',
            'smtp_password' => ($config ? 'nullable' : 'required') . '|string|max:1024',
        ]);

        if (!filled($data['imap_password'] ?? null)) {
            unset($data['imap_password']);
        }
        if (!filled($data['smtp_password'] ?? null)) {
            unset($data['smtp_password']);
        }

        $data['imap_inbox_folder'] = $data['imap_inbox_folder'] ?: 'INBOX';
        $data['imap_sent_folder'] = $data['imap_sent_folder'] ?: 'Sent';
        $data['imap_trash_folder'] = $data['imap_trash_folder'] ?: 'Trash';

        $payload = $data;
        if ($config) {
            if (!array_key_exists('imap_password', $payload)) {
                try {
                    $payload['imap_password'] = $config->imap_password;
                } catch (\Throwable) {
                    // APP_KEY changed — old encrypted value unreadable; user must re-enter
                    unset($payload['imap_password']);
                }
            }
            if (!array_key_exists('smtp_password', $payload)) {
                try {
                    $payload['smtp_password'] = $config->smtp_password;
                } catch (\Throwable) {
                    unset($payload['smtp_password']);
                }
            }
            $payload['imap_inbox_folder'] = $payload['imap_inbox_folder'] ?: $config->imap_inbox_folder;
            $payload['imap_sent_folder'] = $payload['imap_sent_folder'] ?: $config->imap_sent_folder;
            $payload['imap_trash_folder'] = $payload['imap_trash_folder'] ?: $config->imap_trash_folder;
        }

        if (!$config) {
            $config = CrmMailConfig::create(array_merge($data, [
                'user_id' => $user->id,
            ]));
        } else {
            $config->fill($payload)->save();
        }

        return response()->json(['data' => $this->formatConfig($config)]);
    }

    private function formatConfig(CrmMailConfig $config): array
    {
        return [
            'id' => $config->id,
            'from_name' => $config->from_name,
            'from_email' => $config->from_email,
            'imap_host' => $config->imap_host,
            'imap_port' => $config->imap_port,
            'imap_secure' => $config->imap_secure,
            'imap_username' => $config->imap_username,
            'imap_inbox_folder' => $config->imap_inbox_folder,
            'imap_sent_folder' => $config->imap_sent_folder,
            'imap_trash_folder' => $config->imap_trash_folder,
            'smtp_host' => $config->smtp_host,
            'smtp_port' => $config->smtp_port,
            'smtp_secure' => $config->smtp_secure,
            'smtp_username' => $config->smtp_username,
            'imap_password_set' => !empty($config->getRawOriginal('imap_password')),
            'smtp_password_set' => !empty($config->getRawOriginal('smtp_password')),
        ];
    }
}
