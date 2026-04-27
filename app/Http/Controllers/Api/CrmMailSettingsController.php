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
        $isAdmin = in_array($user->role_cached ?? $user->role?->code, ['ADMIN', 'DIRECTOR', 'director', 'admin'], true);

        if (env('IMAP_DEBUG', false)) {
            \Log::channel('mail')->info('Mail settings update request', [
                'user_id' => $user->id,
                'has_config' => (bool) $config,
            ]);
        }

        if ($isAdmin) {
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
        } else {
            // Non-admin users can only update their own login credentials
            $data = $request->validate([
                'imap_username' => 'nullable|string|max:255',
                'imap_password' => 'nullable|string|max:1024',
                'smtp_username' => 'nullable|string|max:255',
                'smtp_password' => 'nullable|string|max:1024',
            ]);
        }

        if (!filled($data['imap_password'] ?? null)) {
            unset($data['imap_password']);
        }
        if (!filled($data['smtp_password'] ?? null)) {
            unset($data['smtp_password']);
        }

        if (env('IMAP_DEBUG', false)) {
            \Log::channel('mail')->info('Mail settings payload', [
                'user_id' => $user->id,
                'imap_user' => $data['imap_username'] ?? null,
                'imap_pass_len' => array_key_exists('imap_password', $data) ? strlen((string) $data['imap_password']) : null,
                'smtp_user' => $data['smtp_username'] ?? null,
                'smtp_pass_len' => array_key_exists('smtp_password', $data) ? strlen((string) $data['smtp_password']) : null,
            ]);
        }

        if ($isAdmin) {
            $data['imap_inbox_folder'] = $data['imap_inbox_folder'] ?: 'INBOX';
            $data['imap_sent_folder'] = $data['imap_sent_folder'] ?: 'Sent';
            $data['imap_trash_folder'] = $data['imap_trash_folder'] ?: 'Trash';
        }

        $payload = $data;
        if ($config) {
            if (!array_key_exists('imap_password', $payload)) {
                $payload['imap_password'] = $config->imap_password;
            }
            if (!array_key_exists('smtp_password', $payload)) {
                $payload['smtp_password'] = $config->smtp_password;
            }
            if ($isAdmin) {
                $payload['imap_inbox_folder'] = $payload['imap_inbox_folder'] ?: $config->imap_inbox_folder;
                $payload['imap_sent_folder'] = $payload['imap_sent_folder'] ?: $config->imap_sent_folder;
                $payload['imap_trash_folder'] = $payload['imap_trash_folder'] ?: $config->imap_trash_folder;
            }
        }

        if (!$config) {
            if (!$isAdmin) {
                return response()->json(['message' => 'Brak konfiguracji poczty. Skontaktuj się z administratorem.'], 422);
            }
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
            'imap_password_set' => !empty($config->imap_password),
            'smtp_password_set' => !empty($config->smtp_password),
        ];
    }
}
