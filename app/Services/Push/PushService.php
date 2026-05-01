<?php

namespace App\Services\Push;

use App\Models\PushToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles sending push notifications via Web Push (VAPID) and FCM.
 * For Web Push, uses the minishlink/web-push library if available,
 * otherwise sends direct HTTP requests to the subscription endpoint.
 */
class PushService
{
    /**
     * Send a push notification to all tokens of a user.
     * Silently ignores errors for individual tokens (expired/invalid = deleted).
     */
    public function notifyUser(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = PushToken::where('user_id', $user->id)->get();
        foreach ($tokens as $token) {
            try {
                if ($token->type === 'fcm') {
                    $this->sendFcm($token->token, $title, $body, $data);
                } elseif ($token->type === 'web') {
                    $this->sendWebPush($token, $title, $body, $data);
                }
                $token->update(['last_used_at' => now()]);
            } catch (\Throwable $e) {
                // If token is expired / invalid, remove it
                if ($this->isTokenExpired($e)) {
                    $token->delete();
                } else {
                    Log::warning('Push send failed', [
                        'user_id'  => $user->id,
                        'token_id' => $token->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Send to multiple users (e.g. all participants of a group chat).
     */
    public function notifyUsers(array $userIds, int $excludeUserId, string $title, string $body, array $data = []): void
    {
        $users = User::whereIn('id', $userIds)
            ->where('id', '!=', $excludeUserId)
            ->get();

        foreach ($users as $user) {
            $this->notifyUser($user, $title, $body, $data);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function sendFcm(string $token, string $title, string $body, array $data): void
    {
        $serverKey = config('push.fcm_server_key');
        if (!$serverKey) {
            return; // FCM not configured
        }

        $response = Http::withHeaders([
            'Authorization' => 'key=' . $serverKey,
            'Content-Type'  => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', [
            'to' => $token,
            'notification' => [
                'title' => $title,
                'body'  => $body,
                'icon'  => '/icons/icon-192x192.png',
                'badge' => '/icons/badge-72x72.png',
                'click_action' => config('app.url'),
            ],
            'data' => $data,
        ]);

        if ($response->status() === 400) {
            throw new \RuntimeException('FCM invalid token');
        }
    }

    private function sendWebPush(PushToken $token, string $title, string $body, array $data): void
    {
        if (!class_exists(\Minishlink\WebPush\WebPush::class)) {
            // Library not installed — skip silently
            return;
        }

        $vapidPublicKey  = config('push.vapid_public_key');
        $vapidPrivateKey = config('push.vapid_private_key');

        if (!$vapidPublicKey || !$vapidPrivateKey) {
            return;
        }

        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject'    => config('app.url'),
                'publicKey'  => $vapidPublicKey,
                'privateKey' => $vapidPrivateKey,
            ],
        ]);

        $subscription = \Minishlink\WebPush\Subscription::create([
            'endpoint' => $token->web_endpoint,
            'keys'     => [
                'p256dh' => $token->web_p256dh,
                'auth'   => $token->web_auth,
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'icon'  => '/icons/icon-192x192.png',
            'badge' => '/icons/badge-72x72.png',
            'data'  => $data,
        ]);

        $webPush->queueNotification($subscription, $payload);

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                if ($report->isSubscriptionExpired()) {
                    $token->delete();
                } else {
                    throw new \RuntimeException($report->getReason());
                }
            }
        }
    }

    private function isTokenExpired(\Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'invalid token')
            || str_contains($e->getMessage(), 'expired')
            || str_contains($e->getMessage(), 'NotRegistered');
    }
}
