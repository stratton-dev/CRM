<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushTokenController extends Controller
{
    /**
     * POST /v1/push-tokens
     * Register or refresh a push token for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'         => ['required', 'string', 'in:web,fcm'],
            'token'        => ['required_if:type,fcm', 'nullable', 'string', 'max:512'],
            'platform'     => ['nullable', 'string', 'in:android,ios,web'],
            'web_endpoint' => ['required_if:type,web', 'nullable', 'string', 'max:1024'],
            'web_p256dh'   => ['required_if:type,web', 'nullable', 'string', 'max:256'],
            'web_auth'     => ['required_if:type,web', 'nullable', 'string', 'max:64'],
        ]);

        $userId = Auth::id();

        if ($data['type'] === 'web') {
            // Use endpoint as unique key for web push
            PushToken::updateOrCreate(
                ['user_id' => $userId, 'web_endpoint' => $data['web_endpoint']],
                [
                    'token'        => $data['web_endpoint'], // endpoint is the identifier
                    'type'         => 'web',
                    'platform'     => 'web',
                    'web_p256dh'   => $data['web_p256dh'],
                    'web_auth'     => $data['web_auth'],
                    'last_used_at' => now(),
                ]
            );
        } else {
            // FCM — token is unique per device
            PushToken::updateOrCreate(
                ['user_id' => $userId, 'token' => $data['token']],
                [
                    'type'         => 'fcm',
                    'platform'     => $data['platform'] ?? null,
                    'last_used_at' => now(),
                ]
            );
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * DELETE /v1/push-tokens
     * Unregister a push token (e.g. on logout).
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'        => ['nullable', 'string'],
            'web_endpoint' => ['nullable', 'string'],
        ]);

        $userId = Auth::id();

        if (!empty($data['web_endpoint'])) {
            PushToken::where('user_id', $userId)
                ->where('web_endpoint', $data['web_endpoint'])
                ->delete();
        } elseif (!empty($data['token'])) {
            PushToken::where('user_id', $userId)
                ->where('token', $data['token'])
                ->delete();
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * GET /v1/push-tokens/vapid-public-key
     * Returns the VAPID public key for Web Push subscription creation.
     */
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'publicKey' => config('push.vapid_public_key'),
        ]);
    }
}
