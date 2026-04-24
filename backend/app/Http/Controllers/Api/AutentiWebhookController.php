<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Autenti\AutentiDocumentService;
use App\Services\Autenti\AutentiOnboardingService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AutentiWebhookController extends Controller
{
    public function __construct(
        private readonly AutentiDocumentService $autenti,
        private readonly AutentiOnboardingService $onboarding
    )
    {
    }

    public function __invoke(Request $request)
    {
        $secret = config('autenti.webhook_secret');
        if (is_string($secret) && $secret !== '') {
            $header = (string) $request->header('X-Autenti-Webhook-Secret', '');
            if (!hash_equals($secret, $header)) {
                return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }
        }

        $payload = $request->all();
        $doc = $this->autenti->handleCallback($payload);
        $onboarding = $this->onboarding->handleCallback($payload);

        if (!$doc && !$onboarding) {
            return response()->json(['message' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['status' => 'ok']);
    }
}
