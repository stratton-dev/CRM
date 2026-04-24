<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupabaseTokenService
{
    public function decode(string $token): array
    {
        $keySet = $this->getKeySet();

        try {
            $decoded = JWT::decode($token, $keySet);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Invalid access token: ' . $exception->getMessage());
        }

        $payload = json_decode(json_encode($decoded), true);
        $this->validateClaims($payload);

        return $payload;
    }

    /**
     * Wyciaga role z pola app_metadata.role lub user_metadata.role
     * zwracajac tablice z jedna rola (Supabase przechowuje role jako skalar).
     */
    public function extractRoles(array $payload): array
    {
        $role = Arr::get($payload, 'app_metadata.role')
            ?? Arr::get($payload, 'user_metadata.role');

        if (!$role) {
            return [];
        }

        return [$role];
    }

    private function validateClaims(array $payload): void
    {
        $expectedIssuer = config('supabase.issuer') ?: $this->defaultIssuer();
        if ($expectedIssuer && Arr::get($payload, 'iss') !== $expectedIssuer) {
            throw new RuntimeException('Invalid token issuer.');
        }

        // Supabase tokeny maja role === "authenticated" – nie sprawdzamy audience
    }

    private function getKeySet(): array
    {
        $ttl = (int) config('supabase.jwks_cache_ttl', 3600);

        $jwks = Cache::remember('supabase.jwks', $ttl, function () {
            $url = config('supabase.jwks_url') ?: $this->defaultJwksUrl();
            if (!$url) {
                throw new RuntimeException('Brak konfiguracji SUPABASE_URL lub SUPABASE_JWKS_URL.');
            }

            $response = Http::get($url);
            if (!$response->ok()) {
                throw new RuntimeException('Nie mozna pobrac kluczy JWKS z Supabase.');
            }

            return $response->json();
        });

        return JWK::parseKeySet($jwks);
    }

    private function defaultIssuer(): ?string
    {
        $url = rtrim((string) config('supabase.url'), '/');
        return $url ? $url . '/auth/v1' : null;
    }

    private function defaultJwksUrl(): ?string
    {
        $url = rtrim((string) config('supabase.url'), '/');
        return $url ? $url . '/auth/v1/.well-known/jwks.json' : null;
    }
}
