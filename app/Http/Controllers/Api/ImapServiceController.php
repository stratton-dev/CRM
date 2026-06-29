<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmMailConfig;
use Illuminate\Http\Request;

class ImapServiceController extends Controller
{
    public function configs(Request $request)
    {
        // Service-to-service endpoint (IMAP node helper) guarded by a shared token.
        // FAIL CLOSED: if the token isn't configured, refuse — otherwise an empty
        // header would pass hash_equals('','') and dump every mailbox password.
        $expected = (string) config('services.imap_service_token', env('IMAP_SERVICE_TOKEN', ''));
        $token = (string) $request->header('X-IMAP-SERVICE-TOKEN', '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $configs = CrmMailConfig::query()->get()->map(static function (CrmMailConfig $config) {
            return [
                'user_id' => $config->user_id,
                'imap' => [
                    'host' => $config->imap_host,
                    'port' => $config->imap_port,
                    'secure' => (bool) $config->imap_secure,
                    'auth' => [
                        'user' => $config->imap_username,
                        'pass' => $config->imap_password,
                    ],
                    'folders' => [
                        'INBOX' => $config->imap_inbox_folder,
                        'SENT' => $config->imap_sent_folder,
                        'TRASH' => $config->imap_trash_folder,
                    ],
                ],
            ];
        })->values();

        return response()->json(['data' => $configs]);
    }
}
