<?php

namespace App\Services\Autenti;

use Autenti\AutentiClient;
use Illuminate\Support\Facades\Cache;

class AutentiClientFactory
{
    public function make(): AutentiClient
    {
        return new AutentiClient([
            'base_uri' => (string) config('autenti.base_uri'),
            'access_token' => $this->resolveAccessToken(),
        ]);
    }

    private function resolveAccessToken(): ?string
    {
        $accessToken = config('autenti.access_token');
        if (is_string($accessToken) && $accessToken !== '') {
            return $accessToken;
        }

        $clientId = config('autenti.client_id');
        $clientSecret = config('autenti.client_secret');
        if (!is_string($clientId) || $clientId === '' || !is_string($clientSecret) || $clientSecret === '') {
            return null;
        }

        return Cache::remember('autenti_access_token', now()->addMinutes(50), function () use ($clientId, $clientSecret) {
            $client = new AutentiClient([
                'base_uri' => (string) config('autenti.base_uri'),
                'access_token' => null,
            ]);

            $response = $client->auth()->tokenWithClientCredentials($clientId, $clientSecret);
            $data = $response->json();

            return $data['access_token'] ?? null;
        });
    }
}
